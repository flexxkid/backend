<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Recruitment;
use App\Services\DocumentStorageService;
use App\Support\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecruitmentController extends Controller
{
    public function __construct(
        private readonly DocumentStorageService $documentStorageService
    ) {
    }

    /**
     * GET /api/recruitment
     * Get all job postings with pagination
     * Public endpoint
     */
    public function index(Request $request): JsonResponse
    {
        $recruitments = Recruitment::with(['department', 'applicants'])
            ->paginate($request->integer('per_page', 15));

        $recruitments->getCollection()->transform(
            fn (Recruitment $recruitment) => $this->appendApplicantDocumentUrlsToRecruitment($recruitment)
        );

        return response()->json($recruitments);
    }

    /**
     * POST /api/recruitment
     * Create new job posting
     * Protected: auth:sanctum, role:HR Administrator
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'JobTitle' => 'required|string|max:150',
            'DepartmentID' => 'nullable|exists:Department,DepartmentID',
            'location' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'type' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'description' => 'required|string|max:300',
            'tags' => 'required|string|max:30',
            'VacancyStatus' => 'nullable|string|max:50',
            'PostedDate' => 'nullable|date',
            'Deadline' => 'required|date|after_or_equal:PostedDate',
        ]);

        // Default values
        $validated['VacancyStatus'] = $validated['VacancyStatus'] ?? 'Open';
        $validated['PostedDate'] = $validated['PostedDate'] ?? now()->toDateString();

        $recruitment = Recruitment::create($validated);

        return response()->json(
            $recruitment->load('department'),
            201
        );
    }

    /**
     * PUT/PATCH /api/recruitment/{recruitmentId}
     * Update job posting
     * Protected: auth:sanctum, role:HR Administrator
     * NEW ENDPOINT - Required by frontend
     */
    public function update(Request $request, int $recruitmentId): JsonResponse
    {
        $recruitment = Recruitment::findOrFail($recruitmentId);

        $validated = $request->validate([
            'JobTitle' => 'sometimes|required|string|max:150',
            'DepartmentID' => 'nullable|exists:Department,DepartmentID',
            'location' => 'sometimes|required|string|max:100',
            'category' => 'sometimes|required|string|max:100',
            'type' => 'sometimes|required|string|max:100',
            'salary' => 'sometimes|required|numeric|min:0',
            'description' => 'sometimes|required|string|max:300',
            'tags' => 'sometimes|required|string|max:30',
            'VacancyStatus' => 'sometimes|required|string|in:Open,Closed',
            'Deadline' => 'sometimes|required|date',
        ]);

        $recruitment->update($validated);

        return response()->json(
            $recruitment->load('department'),
            200
        );
    }

    /**
     * DELETE /api/recruitment/{recruitmentId}
     * Delete job posting
     * Protected: auth:sanctum, role:HR Administrator
     * NEW ENDPOINT - Required by frontend
     */
    public function destroy(int $recruitmentId): JsonResponse
    {
        $recruitment = Recruitment::findOrFail($recruitmentId);
        $recruitment->delete();

        return response()->json(['message' => 'Recruitment posting deleted successfully'], 200);
    }

    /**
     * POST /api/recruitment/{recruitmentId}/apply
     * Apply for a job posting
     * Public endpoint
     */
    public function apply(Request $request, int $recruitmentId): JsonResponse
    {
        $validated = $request->validate([
            'FullName' => 'nullable|string|max:200',
            'FirstName' => 'required_without:FullName|string|max:100',
            'LastName' => 'required_without:FullName|string|max:100',
            'DateOfBirth' => 'nullable|date',
            'Email' => 'nullable|email|max:150',
            'Address' => 'nullable|string|max:255',
            'PhoneNumber' => 'nullable|string|max:20',
            'Gender' => 'nullable|string|max:20',
            'NationalID' => 'required|string|max:50|unique:Applicant,NationalID',
            'ApplicationStatus' => 'nullable|string|max:50',
            'LetterOfApplication' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'HighestLevelCertificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'CV' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'GoodConduct' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $validated = PersonName::normalizePayload($validated, true);
        $validated = $this->storeApplicantFiles($request, $recruitmentId, $validated);

        $applicant = Applicant::create([
            ...$validated,
            'RecruitmentID' => $recruitmentId,
            'ApplicationStatus' => $validated['ApplicationStatus'] ?? 'Submitted',
        ]);

        return response()->json(
            $this->appendApplicantDocumentUrls(
                $applicant->load('recruitment')
            ),
            201
        );
    }

    /**
     * GET /api/recruitments/{recruitmentId}/applications
     * Get all applications for a posting
     * Protected endpoint
     */
    public function viewApplications(Request $request, int $recruitmentId): JsonResponse
    {
        $applications = Applicant::where('RecruitmentID', $recruitmentId)
            ->orderByDesc('ApplicationID')
            ->paginate($request->integer('per_page', 15));

        $applications->getCollection()->transform(
            fn (Applicant $applicant) => $this->appendApplicantDocumentUrls($applicant)
        );

        return response()->json($applications);
    }

    /**
     * GET /api/applications/{applicantId}
     * Get single application
     * Protected endpoint
     */
    public function viewApplication(int $applicantId): JsonResponse
    {
        $application = Applicant::with('recruitment')
            ->findOrFail($applicantId);

        return response()->json(
            $this->appendApplicantDocumentUrls($application)
        );
    }

    public function documentUrl(int $applicantId, string $field): JsonResponse
    {
        abort_unless(
            in_array($field, ['LetterOfApplication', 'HighestLevelCertificate', 'CV', 'GoodConduct'], true),
            404
        );

        $applicant = Applicant::findOrFail($applicantId);
        $url = $this->applicantDocumentUrl($applicant, $field);

        abort_unless($url, 404, 'Document file not found');

        return response()->json(['url' => $url]);
    }

    public function showApplicantDocument(Request $request, int $applicantId, string $field): BinaryFileResponse
    {
        abort_unless(
            in_array($field, ['LetterOfApplication', 'HighestLevelCertificate', 'CV', 'GoodConduct'], true),
            404
        );

        $applicant = Applicant::findOrFail($applicantId);
        $path = $applicant->{$field};

        abort_unless(filled($path), 404);

        $disk = Storage::disk($this->documentStorageService->disk());
        $absolutePath = $disk->path($path);

        abort_unless(is_file($absolutePath), 404);

        return response()->file(
            $absolutePath,
            [
                'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            ]
        );
    }

    /**
     * PATCH /api/applicants/{applicantId}
     * Update applicant status (Approved, Rejected, etc)
     * Protected: auth:sanctum, role:HR Administrator
     */
    public function updateApplicantStatus(Request $request, int $applicantId): JsonResponse
    {
        $applicant = Applicant::findOrFail($applicantId);
        $validated = $request->validate([
            'FullName' => 'nullable|string|max:200',
            'FirstName' => 'required_without:FullName|string|max:100',
            'LastName' => 'required_without:FullName|string|max:100',
            'DateOfBirth' => 'nullable|date',
            'Email' => 'nullable|email|max:150',
            'Address' => 'nullable|string|max:255',
            'PhoneNumber' => 'nullable|string|max:20',
            'Gender' => 'nullable|string|max:20',
            'LetterOfApplication' => 'nullable|string|max:500',
            'HighestLevelCertificate' => 'nullable|string|max:255',
            'CV' => 'nullable|string|max:500',
            'ApplicationStatus' => 'nullable|string|in:Submitted,Approved,Rejected,Shortlisted,Hired,Pending',
            'GoodConduct' => 'nullable|string|max:500',
            'NationalID' => 'required|string|max:50|unique:Applicant,NationalID,'.$applicantId.',ApplicationID',
            'RecruitmentID' => 'nullable|exists:Recruitment,RecruitmentID',
        ]);

        $applicant->update(PersonName::normalizePayload($validated, false));

        return response()->json(
            $this->appendApplicantDocumentUrls(
                $applicant->load('recruitment')
            ),
            200
        );
    }

    /**
     * POST /api/applicants/{applicantId}/convert
     * Convert applicant to employee (HIRE)
     * Protected: auth:sanctum, role:HR Administrator
     */
    public function convertApplicant(Request $request, int $applicantId): JsonResponse
    {
        $validated = $request->validate([
            'HireDate' => 'required|date',
            'DepartmentID' => 'required|exists:Department,DepartmentID',
            'BranchID' => 'required|exists:Branch,BranchID',
            'JobTitle' => 'required|string|max:150',
            'EmploymentStatus' => 'required|in:Active,Inactive,Suspended',
            'SupervisorID' => 'nullable|exists:Employee,EmployeeID',
        ]);

        $employee = DB::transaction(function () use ($applicantId, $validated) {
            $applicant = Applicant::findOrFail($applicantId);

            // Create employee from applicant data
            $employee = Employee::create([
                'FullName' => $applicant->FullName,
                'DateOfBirth' => $applicant->DateOfBirth,
                'Email' => $applicant->Email,
                'PostalAddress' => $applicant->Address,
                'PhoneNumber' => $applicant->PhoneNumber,
                'Gender' => $applicant->Gender,
                'JobTitle' => $validated['JobTitle'],
                'LetterOfApplication' => $applicant->LetterOfApplication,
                'HighestLevelCertificate' => $applicant->HighestLevelCertificate,
                'CV' => $applicant->CV,
                'ApplicationStatus' => 'Hired',
                'GoodConduct' => $applicant->GoodConduct,
                'NationalID' => $applicant->NationalID,
                'HireDate' => $validated['HireDate'],
                'EmploymentStatus' => $validated['EmploymentStatus'],
                'DepartmentID' => $validated['DepartmentID'],
                'SupervisorID' => $validated['SupervisorID'] ?? null,
                'BranchID' => $validated['BranchID'],
            ]);

            // Update applicant status to Hired
            $applicant->update([
                'ApplicationStatus' => 'Hired'
            ]);

            return $employee;
        });

        return response()->json(
            $employee->load(['department', 'branch', 'supervisor']),
            201
        );
    }

    /**
     * Helper: Store applicant files in B2 storage
     * Returns storage path that can be used with download endpoint
     */
    private function storeApplicantFiles(
        Request $request,
        int $recruitmentId,
        array $validated
    ): array {
        $applicationDirectory = 'recruitment/' . $recruitmentId .
            '/applications/' . Str::uuid();

        $fileFields = [
            'LetterOfApplication' => 'letter-of-application',
            'HighestLevelCertificate' => 'highest-level-certificate',
            'CV' => 'cv',
            'GoodConduct' => 'good-conduct',
        ];

        foreach ($fileFields as $field => $prefix) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);

            if (!$file->isValid()) {
                logger()->warning("Invalid upload detected: {$field}");
                continue;
            }

            // Store and get the path
            $storedPath = $this->documentStorageService->store(
                $file,
                $applicationDirectory,
                $prefix . '.' . $file->getClientOriginalExtension()
            );

            // If it's a B2 URL, store it as-is
            // If it's a local path, store it as-is for download endpoint
            $validated[$field] = $storedPath;
        }

        return $validated;
    }

    private function appendApplicantDocumentUrls(Applicant $applicant): Applicant
    {
        foreach ([
            'LetterOfApplication',
            'HighestLevelCertificate',
            'CV',
            'GoodConduct',
        ] as $field) {
            $applicant->setAttribute(
                $field . 'Url',
                $this->applicantDocumentUrl($applicant, $field)
            );
        }

        return $applicant;
    }

    private function appendApplicantDocumentUrlsToRecruitment(Recruitment $recruitment): Recruitment
    {
        if ($recruitment->relationLoaded('applicants')) {
            $recruitment->setRelation(
                'applicants',
                $recruitment->applicants->map(
                    fn (Applicant $applicant) => $this->appendApplicantDocumentUrls($applicant)
                )
            );
        }

        return $recruitment;
    }

    private function applicantDocumentUrl(Applicant $applicant, string $field): ?string
    {
        $path = $applicant->{$field};

        if (! filled($path)) {
            return null;
        }

        $disk = Storage::disk($this->documentStorageService->disk());

        if (! $disk->exists($path)) {
            return null;
        }

        if ($this->documentStorageService->disk() !== 'local') {
            return $this->documentStorageService->url($path);
        }

        return URL::temporarySignedRoute(
            'applicants.documents.show',
            now()->addMinutes(5),
            [
                'applicantId' => $applicant->ApplicationID,
                'field' => $field,
            ]
        );
    }
}
