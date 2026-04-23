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
use Illuminate\Support\Str;

class RecruitmentController extends Controller
{
    public function __construct(private readonly DocumentStorageService $documentStorageService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Recruitment::with(['department', 'applicants'])->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'JobTitle' => 'required|string|max:150',
            'DepartmentID' => 'required|exists:Department,DepartmentID',
            'VacancyStatus' => 'required|string|max:50',
            'PostedDate' => 'nullable|date',
        ]);

        $recruitment = Recruitment::create($validated);

        return response()->json($recruitment->load('department'), 201);
    }

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
            'LetterOfApplication' => 'nullable',
            'HighestLevelCertificate' => 'nullable',
            'CV' => 'nullable',
            'ApplicationStatus' => 'nullable|string|max:50',
            'GoodConduct' => 'nullable',
            'NationalID' => 'required|string|max:50|unique:Applicant,NationalID',
        ]);

        $request->validate([
            'LetterOfApplication' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'HighestLevelCertificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'CV' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'GoodConduct' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $validated = PersonName::normalizePayload($validated, true);
        $validated = $this->storeApplicantFiles($request, $recruitmentId, $validated);

        $applicant = Applicant::create($validated + [
            'RecruitmentID' => $recruitmentId,
            'ApplicationStatus' => $validated['ApplicationStatus'] ?? 'Submitted',
        ]);

        return response()->json($applicant->load('recruitment'), 201);
    }

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

            $applicant->update(['ApplicationStatus' => 'Hired']);

            return $employee;
        });

        return response()->json($employee->load(['department', 'branch', 'supervisor']), 201);
    }

    private function storeApplicantFiles(Request $request, int $recruitmentId, array $validated): array
    {
        $applicationDirectory = 'recruitment/'.(int) $recruitmentId.'/applications/'.Str::uuid();

        $fileFields = [
            'LetterOfApplication' => 'letter-of-application',
            'HighestLevelCertificate' => 'highest-level-certificate',
            'CV' => 'cv',
            'GoodConduct' => 'good-conduct',
        ];

        foreach ($fileFields as $field => $prefix) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);
            $validated[$field] = $this->documentStorageService->store(
                $file,
                $applicationDirectory,
                $prefix.'.'.$file->getClientOriginalExtension(),
            );
        }

        return $validated;
    }
}
