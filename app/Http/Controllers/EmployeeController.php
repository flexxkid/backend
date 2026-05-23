<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\UserAccount;
use App\Services\DocumentStorageService;
use App\Support\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(private readonly DocumentStorageService $documentStorageService)
    {
    }

    public function index(Request $request): JsonResponse|StreamedResponse|Response
    {
        $query = Employee::query()
            ->with(['department', 'branch', 'supervisor'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = strtolower((string) $request->string('search'));
                $query->where(function ($nested) use ($search) {
                    $nested
                        ->whereRaw('LOWER(FullName) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(NationalID) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(Email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(JobTitle) LIKE ?', ["%{$search}%"]);
                });
            })
            ->when($request->filled('DepartmentID'), fn ($query) => $query->where('DepartmentID', $request->integer('DepartmentID')))
            ->when($request->filled('BranchID'), fn ($query) => $query->where('BranchID', $request->integer('BranchID')))
            ->when($request->filled('EmploymentStatus'), fn ($query) => $query->where('EmploymentStatus', $request->string('EmploymentStatus')));

        if ($request->user()?->role?->RoleName === 'Branch Manager') {
            $query->where('BranchID', $request->user()->employee?->BranchID)
                ->where('EmployeeID', '!=', $request->user()->EmployeeID);
        }

        $export = $request->string('export')->lower()->toString();

        if ($export === 'csv') {
            return response()->streamDownload(function () use ($query) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['EmployeeID', 'FullName', 'Email', 'NationalID', 'JobTitle', 'EmploymentStatus']);
                foreach ($query->cursor() as $employee) {
                    fputcsv($handle, [
                        $employee->EmployeeID,
                        $employee->FullName,
                        $employee->Email,
                        $employee->NationalID,
                        $employee->JobTitle,
                        $employee->EmploymentStatus,
                    ]);
                }
                fclose($handle);
            }, 'employees.csv', ['Content-Type' => 'text/csv']);
        }

        if ($export === 'pdf') {
            return new Response(
                $this->buildEmployeePdf($query->get()),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="employees.pdf"',
                ]
            );
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create(PersonName::normalizePayload($request->validated(), true));

        return response()->json($employee->load(['department', 'branch']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::with([
            'department',
            'branch',
            'supervisor',
            'documents.documentType',
            'leaveBalances',
            'deploymentHistories.branch',
            'performanceEvaluations.evaluator',
        ])->findOrFail($id);

        $roleName = request()->user()?->role?->RoleName;

        if ($roleName === 'Branch Manager'
            && $employee->BranchID !== request()->user()?->employee?->BranchID) {
            abort(403, 'Forbidden: insufficient role');
        }

        if ($roleName === 'Employee'
            && (int) $employee->EmployeeID !== (int) request()->user()?->EmployeeID) {
            abort(403, 'Forbidden: insufficient role');
        }

        return response()->json($this->appendApplicationDocumentUrls($employee));
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update(PersonName::normalizePayload($request->validated()));

        return response()->json($employee->load(['department', 'branch', 'supervisor']));
    }

    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['EmploymentStatus' => 'Inactive']);
        UserAccount::query()
            ->where('EmployeeID', $employee->EmployeeID)
            ->each(function (UserAccount $account): void {
                $account->update(['AccountStatus' => 'inactive']);
                $account->tokens()->delete();
            });

        return response()->json(['message' => 'Employee deactivated']);
    }

    public function applicationDocumentUrl(Request $request, int $employeeId, string $field): JsonResponse
    {
        abort_unless(
            in_array($field, ['LetterOfApplication', 'HighestLevelCertificate', 'CV', 'GoodConduct'], true),
            404
        );

        $employee = Employee::findOrFail($employeeId);

        $roleName = $request->user()?->role?->RoleName;

        if ($roleName === 'Branch Manager'
            && $employee->BranchID !== $request->user()?->employee?->BranchID) {
            abort(403, 'Forbidden: insufficient role');
        }

        if ($roleName === 'Employee'
            && (int) $employee->EmployeeID !== (int) $request->user()?->EmployeeID) {
            abort(403, 'Forbidden: insufficient role');
        }

        $url = $this->employeeApplicationDocumentUrl($employee, $field);

        abort_unless($url, 404, 'Document file not found');

        return response()->json(['url' => $url]);
    }

    private function buildEmployeePdf($employees): string
    {
        $lines = [
            'GardaWorld HRMS Employee Export',
            'Generated: '.now()->toDateTimeString(),
            '',
        ];

        foreach ($employees as $employee) {
            $lines[] = sprintf(
                '#%s | %s | %s | %s | %s | %s',
                $employee->EmployeeID,
                $employee->FullName,
                $employee->Email ?? '-',
                $employee->NationalID,
                $employee->JobTitle ?? '-',
                $employee->EmploymentStatus ?? '-'
            );
        }

        $content = "BT\n/F1 10 Tf\n50 780 Td\n";

        foreach (array_slice($lines, 0, 45) as $index => $line) {
            if ($index > 0) {
                $content .= "0 -16 Td\n";
            }

            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= "({$escaped}) Tj\n";
        }

        $content .= "ET";

        return $this->renderPdfDocument($content);
    }

    private function renderPdfDocument(string $content): string
    {
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj',
            sprintf("4 0 obj << /Length %d >> stream\n%s\nendstream endobj", strlen($content), $content),
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }

        $pdf .= "trailer << /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    public function streamApplicationDocument(int $employeeId, string $field): Response
    {
        abort_unless(
            in_array($field, ['LetterOfApplication', 'HighestLevelCertificate', 'CV', 'GoodConduct'], true),
            404
        );

        $employee = Employee::findOrFail($employeeId);
        $path = $employee->{$field};

        abort_unless(filled($path), 404);
        abort_unless($this->documentStorageService->isLocalDisk(), 404);

        $disk = Storage::disk($this->documentStorageService->disk());
        $absolutePath = $disk->path($path);

        abort_unless(is_file($absolutePath), 404);

        return response()->file(
            $absolutePath,
            ['Content-Disposition' => 'inline; filename="' . basename($path) . '"']
        );
    }

    private function appendApplicationDocumentUrls(Employee $employee): Employee
    {
        foreach (['LetterOfApplication', 'HighestLevelCertificate', 'CV', 'GoodConduct'] as $field) {
            $employee->setAttribute($field . 'Url', $this->employeeApplicationDocumentUrl($employee, $field));
        }

        return $employee;
    }

    private function employeeApplicationDocumentUrl(Employee $employee, string $field): ?string
    {
        $path = $employee->{$field};

        if (! filled($path)) {
            return null;
        }

        $disk = Storage::disk($this->documentStorageService->disk());

        if (! $disk->exists($path)) {
            return null;
        }

        if (! $this->documentStorageService->isLocalDisk()) {
            return $this->documentStorageService->url($path);
        }

        return URL::temporarySignedRoute(
            'employees.application-documents.show',
            now()->addMinutes(5),
            [
                'employeeId' => $employee->EmployeeID,
                'field' => $field,
            ]
        );
    }
}
