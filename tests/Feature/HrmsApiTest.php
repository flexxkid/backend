<?php

namespace Tests\Feature;

use App\Models\Allowances;
use App\Models\Applicant;
use App\Models\Branch;
use App\Models\Deductions;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Permission;
use App\Models\Recruitment;
use App\Models\Role;
use App\Models\Training;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrmsApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider resourceProvider
     */
    public function test_crud_endpoints_for_hrms_resources(string $resource): void
    {
        $context = $this->seedContext();

        Sanctum::actingAs($context['user']);

        $storePayload = $this->payloadFor($resource, $context);
        $storeResponse = $this->postJson("/api/{$resource}", $storePayload);

        $storeResponse->assertCreated();

        $recordId = $this->extractId($resource, $storeResponse->json());

        $this->getJson("/api/{$resource}")
            ->assertOk();

        $this->getJson("/api/{$resource}/{$recordId}")
            ->assertOk();

        $updatePayload = $this->updatePayloadFor($resource, $context, $storePayload);

        $this->patchJson("/api/{$resource}/{$recordId}", $updatePayload)
            ->assertOk();

        $deleteResponse = $this->deleteJson("/api/{$resource}/{$recordId}");

        if (in_array($resource, ['employees', 'user-accounts'], true)) {
            $deleteResponse->assertOk();

            return;
        }

        $deleteResponse->assertNoContent();
    }

    public function test_audit_logs_are_read_only_and_populated_by_api_actions(): void
    {
        $context = $this->seedContext();

        Sanctum::actingAs($context['user']);

        $this->postJson('/api/branches', [
            'BranchName' => 'Mombasa Annex',
            'BranchLocation' => 'Mombasa',
        ])->assertCreated();

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.Action', 'create');

        $this->postJson('/api/audit-logs', [
            'Action' => 'manual',
            'AffectedTable' => 'Branch',
        ])->assertStatus(405);
    }

    public function test_document_upload_creates_additional_document_record(): void
    {
        Storage::fake('local');

        $context = $this->seedContext();

        Sanctum::actingAs($context['user']);

        $response = $this->post('/api/upload', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'DocumentTypeID' => $context['documentType']->DocumentTypeID,
            'Description' => 'National ID copy',
            'file' => UploadedFile::fake()->create('national-id.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('document.EmployeeID', $context['employee']->EmployeeID);

        Storage::disk('local')->assertExists($response->json('document.Document'));
    }

    public function test_employee_document_upload_stores_file_on_local_disk(): void
    {
        Storage::fake('local');

        $context = $this->seedContext();

        Sanctum::actingAs($context['user']);

        $response = $this->post("/api/employees/{$context['employee']->EmployeeID}/documents", [
            'DocumentTypeID' => $context['documentType']->DocumentTypeID,
            'Description' => 'Contract copy',
            'file' => UploadedFile::fake()->create('contract.pdf', 160, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('EmployeeID', $context['employee']->EmployeeID);

        Storage::disk('local')->assertExists($response->json('Document'));
    }

    public function test_upload_document_endpoint_stores_file_on_b2_disk_when_configured(): void
    {
        Storage::fake('b2');
        $this->configureB2Disk();

        $context = $this->seedContext();

        Sanctum::actingAs($context['user']);

        $response = $this->post('/api/upload', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'DocumentTypeID' => $context['documentType']->DocumentTypeID,
            'Description' => 'Good conduct certificate',
            'file' => UploadedFile::fake()->create('good-conduct.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('document.EmployeeID', $context['employee']->EmployeeID);

        Storage::disk('b2')->assertExists($response->json('document.Document'));
    }

    public function test_employee_document_upload_stores_file_on_b2_disk_when_configured(): void
    {
        Storage::fake('b2');
        $this->configureB2Disk();

        $context = $this->seedContext();

        Sanctum::actingAs($context['user']);

        $response = $this->post("/api/employees/{$context['employee']->EmployeeID}/documents", [
            'DocumentTypeID' => $context['documentType']->DocumentTypeID,
            'Description' => 'NHIF card',
            'file' => UploadedFile::fake()->create('nhif-card.pdf', 80, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('EmployeeID', $context['employee']->EmployeeID);

        Storage::disk('b2')->assertExists($response->json('Document'));
    }

    public function test_employee_and_applicant_fullname_are_built_from_first_and_last_name(): void
    {
        $context = $this->seedContext();

        Sanctum::actingAs($context['user']);

        $employee = $this->postJson('/api/employees', [
            'FirstName' => 'Mary',
            'LastName' => 'Recruit',
            'DateOfBirth' => '1997-07-07',
            'Email' => 'mary.recruit@example.com',
            'PostalAddress' => 'P.O. Box 4',
            'PhoneNumber' => '0755555555',
            'Gender' => 'Female',
            'JobTitle' => 'Accountant',
            'NationalID' => 'EMP-301',
            'HireDate' => '2026-04-20',
            'EmploymentStatus' => 'Active',
            'DepartmentID' => $context['department']->DepartmentID,
            'SupervisorID' => $context['supervisor']->EmployeeID,
            'BranchID' => $context['branch']->BranchID,
        ])->assertCreated();

        $employee->assertJsonPath('FullName', 'Mary Recruit');

        $applicant = $this->postJson('/api/applicants', [
            'FirstName' => 'Alice',
            'LastName' => 'Applicant',
            'DateOfBirth' => '1998-08-20',
            'Email' => 'alice@applicant.test',
            'Address' => 'P.O. Box 3',
            'PhoneNumber' => '0744444444',
            'Gender' => 'Female',
            'LetterOfApplication' => 'letter.pdf',
            'HighestLevelCertificate' => 'degree.pdf',
            'CV' => 'cv.pdf',
            'ApplicationStatus' => 'Pending',
            'GoodConduct' => 'gc.pdf',
            'NationalID' => 'APP-300',
            'RecruitmentID' => $context['recruitment']->RecruitmentID,
        ])->assertCreated();

        $applicant->assertJsonPath('FullName', 'Alice Applicant');
    }

    public function test_recruitment_application_stores_uploaded_files_on_local_disk(): void
    {
        Storage::fake('local');

        $context = $this->seedContext();

        $response = $this->post("/api/recruitment/{$context['recruitment']->RecruitmentID}/apply", [
            'FirstName' => 'Upload',
            'LastName' => 'Applicant',
            'Email' => 'upload.applicant@example.com',
            'NationalID' => 'APP-UPLOAD-001',
            'LetterOfApplication' => UploadedFile::fake()->create('letter.pdf', 80, 'application/pdf'),
            'HighestLevelCertificate' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
            'CV' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
            'GoodConduct' => UploadedFile::fake()->create('good-conduct.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('FullName', 'Upload Applicant')
            ->assertJsonPath('RecruitmentID', $context['recruitment']->RecruitmentID);

        $paths = Applicant::query()
            ->where('NationalID', 'APP-UPLOAD-001')
            ->firstOrFail([
                'LetterOfApplication',
                'HighestLevelCertificate',
                'CV',
                'GoodConduct',
            ]);

        Storage::disk('local')->assertExists($paths->LetterOfApplication);
        Storage::disk('local')->assertExists($paths->HighestLevelCertificate);
        Storage::disk('local')->assertExists($paths->CV);
        Storage::disk('local')->assertExists($paths->GoodConduct);
    }

    public function test_recruitment_application_stores_uploaded_files_on_b2_when_configured(): void
    {
        Storage::fake('b2');
        $this->configureB2Disk();

        $context = $this->seedContext();

        $this->post("/api/recruitment/{$context['recruitment']->RecruitmentID}/apply", [
            'FirstName' => 'Cloud',
            'LastName' => 'Applicant',
            'Email' => 'cloud.applicant@example.com',
            'NationalID' => 'APP-UPLOAD-002',
            'LetterOfApplication' => UploadedFile::fake()->create('letter.pdf', 80, 'application/pdf'),
            'HighestLevelCertificate' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
            'CV' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
            'GoodConduct' => UploadedFile::fake()->create('good-conduct.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $paths = Applicant::query()
            ->where('NationalID', 'APP-UPLOAD-002')
            ->firstOrFail([
                'LetterOfApplication',
                'HighestLevelCertificate',
                'CV',
                'GoodConduct',
            ]);

        Storage::disk('b2')->assertExists($paths->LetterOfApplication);
        Storage::disk('b2')->assertExists($paths->HighestLevelCertificate);
        Storage::disk('b2')->assertExists($paths->CV);
        Storage::disk('b2')->assertExists($paths->GoodConduct);
    }

    public function test_leave_approval_updates_leave_balance(): void
    {
        $context = $this->seedContext();
        Sanctum::actingAs($context['user']);

        $this->postJson('/api/leave-balances', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'LeaveType' => 'Annual',
            'TotalDays' => 21,
            'UsedDays' => 0,
        ])->assertCreated();

        $leave = $this->postJson('/api/leave', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'LeaveType' => 'Annual',
            'StartDate' => '2026-05-10',
            'EndDate' => '2026-05-12',
            'Reason' => 'Family event',
        ])->assertCreated()->json();

        $this->patchJson("/api/leave/{$leave['LeaveID']}/approve", [
            'status' => 'Approved',
        ])->assertOk();

        $this->getJson('/api/leave-balances')
            ->assertOk()
            ->assertJsonPath('data.0.UsedDays', 3)
            ->assertJsonPath('data.0.RemainingDays', 18);
    }

    public function test_payroll_processing_computes_net_salary_from_assignments(): void
    {
        $context = $this->seedContext();
        Sanctum::actingAs($context['user']);

        $allowance = $this->postJson('/api/assigned-allowances', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'AllowanceID' => $context['allowance']->AllowanceID,
            'EffectiveDate' => '2026-04-01',
            'Amount' => 5000,
        ])->assertCreated()->json();

        $deduction = $this->postJson('/api/assigned-deductions', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'DeductionID' => $context['deduction']->DeductionID,
            'EffectiveDate' => '2026-04-01',
            'Amount' => 2000,
        ])->assertCreated()->json();

        $this->assertNotEmpty($allowance['AssignedAllowanceID']);
        $this->assertNotEmpty($deduction['AssignedDeductionID']);

        $this->postJson('/api/payroll', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'PayPeriod' => '2026-06',
            'BasicSalary' => 50000,
            'PaymentDate' => '2026-06-30',
        ])->assertCreated()
            ->assertJsonPath('NetSalary', 53000);
    }

    public function test_applicant_can_be_converted_to_employee(): void
    {
        $context = $this->seedContext();
        Sanctum::actingAs($context['user']);

        $applicant = $this->postJson("/api/recruitment/{$context['recruitment']->RecruitmentID}/apply", [
            'FullName' => 'Candidate Convert',
            'Email' => 'candidate@example.com',
            'NationalID' => 'APP-901',
        ])->assertCreated()->json();

        $this->postJson("/api/applicants/{$applicant['ApplicationID']}/convert", [
            'HireDate' => '2026-07-01',
            'DepartmentID' => $context['department']->DepartmentID,
            'BranchID' => $context['branch']->BranchID,
            'JobTitle' => 'Guard',
            'EmploymentStatus' => 'Active',
            'SupervisorID' => $context['supervisor']->EmployeeID,
        ])->assertCreated()
            ->assertJsonPath('NationalID', 'APP-901');
    }

    public static function resourceProvider(): array
    {
        return [
            ['roles'],
            ['permissions'],
            ['role-permissions'],
            ['branches'],
            ['document-types'],
            ['allowances'],
            ['deductions'],
            ['trainings'],
            ['departments'],
            ['recruitments'],
            ['applicants'],
            ['employees'],
            ['user-accounts'],
            ['notifications'],
            ['attendance'],
            ['leave-requests'],
            ['leave-balances'],
            ['additional-documents'],
            ['deployment-history'],
            ['employee-trainings'],
            ['performance-evaluations'],
            ['payrolls'],
            ['assigned-allowances'],
            ['payroll-allowances'],
            ['assigned-deductions'],
            ['payroll-deductions'],
        ];
    }

    private function seedContext(): array
    {
        $branch = Branch::create([
            'BranchName' => 'HQ',
            'BranchLocation' => 'Nairobi',
            'BranchPhone' => '0700000000',
            'BranchEmail' => 'hq@example.com',
        ]);

        $role = Role::create([
            'RoleName' => 'HR Administrator',
            'RoleDescription' => 'System administrator',
        ]);

        $permission = Permission::create([
            'PermissionName' => 'manage-system',
            'Description' => 'Can manage the HRMS',
        ]);

        $documentType = DocumentType::create([
            'TypeName' => 'National ID',
            'TypeDescription' => 'Government-issued identification',
        ]);

        $allowance = Allowances::create([
            'AllowanceName' => 'Housing',
        ]);

        $deduction = Deductions::create([
            'DeductionName' => 'PAYE',
            'Rate' => 0.3,
        ]);

        $training = Training::create([
            'TrainingName' => 'Orientation',
            'TrainingType' => 'Mandatory',
            'StartDate' => '2026-04-01',
            'EndDate' => '2026-04-02',
        ]);

        $department = Department::create([
            'DepartmentName' => 'Operations',
            'BranchID' => $branch->BranchID,
            'DepartmentDescription' => 'Operations department',
            'CreatedDate' => '2026-04-01',
        ]);

        $supervisor = Employee::create([
            'FullName' => 'Jane Supervisor',
            'DateOfBirth' => '1990-02-01',
            'Email' => 'jane.supervisor@example.com',
            'PostalAddress' => 'P.O. Box 1',
            'PhoneNumber' => '0711111111',
            'Gender' => 'Female',
            'JobTitle' => 'Head of Operations',
            'NationalID' => 'SUP-100',
            'HireDate' => '2024-01-01',
            'EmploymentStatus' => 'Active',
            'DepartmentID' => $department->DepartmentID,
            'BranchID' => $branch->BranchID,
        ]);

        $department->update(['HODID' => $supervisor->EmployeeID]);

        $employee = Employee::create([
            'FullName' => 'John Employee',
            'DateOfBirth' => '1995-03-10',
            'Email' => 'john.employee@example.com',
            'PostalAddress' => 'P.O. Box 2',
            'PhoneNumber' => '0722222222',
            'Gender' => 'Male',
            'JobTitle' => 'Officer',
            'NationalID' => 'EMP-200',
            'HireDate' => '2025-01-10',
            'EmploymentStatus' => 'Active',
            'DepartmentID' => $department->DepartmentID,
            'SupervisorID' => $supervisor->EmployeeID,
            'BranchID' => $branch->BranchID,
        ]);

        $user = UserAccount::create([
            'EmployeeID' => $employee->EmployeeID,
            'Username' => 'api.admin',
            'PasswordHash' => bcrypt('secret123'),
            'RoleID' => $role->RoleID,
            'AccountStatus' => 'active',
        ]);

        $recruitment = Recruitment::create([
            'JobTitle' => 'Security Guard',
            'DepartmentID' => $department->DepartmentID,
            'VacancyStatus' => 'Open',
            'PostedDate' => '2026-04-01',
        ]);

        $payroll = Payroll::create([
            'EmployeeID' => $employee->EmployeeID,
            'PayPeriod' => '2026-04',
            'BasicSalary' => 50000,
            'NetSalary' => 45000,
            'PaymentDate' => '2026-04-30',
        ]);

        return compact(
            'branch',
            'role',
            'permission',
            'documentType',
            'allowance',
            'deduction',
            'training',
            'department',
            'supervisor',
            'employee',
            'user',
            'recruitment',
            'payroll',
        );
    }

    private function payloadFor(string $resource, array $context): array
    {
        return match ($resource) {
            'roles' => [
                'RoleName' => 'HR Officer',
                'RoleDescription' => 'Handles HR workflows',
            ],
            'permissions' => [
                'PermissionName' => 'view-payroll',
                'Description' => 'Can view payroll',
            ],
            'role-permissions' => [
                'RoleID' => $context['role']->RoleID,
                'PermissionID' => $context['permission']->PermissionID,
            ],
            'branches' => [
                'BranchName' => 'Kisumu',
                'BranchLocation' => 'Kisumu',
                'BranchPhone' => '0733333333',
                'BranchEmail' => 'kisumu@example.com',
            ],
            'document-types' => [
                'TypeName' => 'Academic Certificate',
                'TypeDescription' => 'Academic document',
            ],
            'allowances' => [
                'AllowanceName' => 'Transport',
            ],
            'deductions' => [
                'DeductionName' => 'NSSF',
                'Rate' => 0.06,
            ],
            'trainings' => [
                'TrainingName' => 'First Aid',
                'TrainingType' => 'Certification',
                'StartDate' => '2026-05-01',
                'EndDate' => '2026-05-03',
            ],
            'departments' => [
                'DepartmentName' => 'Finance',
                'HODID' => $context['supervisor']->EmployeeID,
                'BranchID' => $context['branch']->BranchID,
                'DepartmentDescription' => 'Finance department',
                'CreatedDate' => '2026-04-15',
            ],
            'recruitments' => [
                'JobTitle' => 'HR Assistant',
                'DepartmentID' => $context['department']->DepartmentID,
                'VacancyStatus' => 'Open',
                'PostedDate' => '2026-04-20',
            ],
            'applicants' => [
                'FullName' => 'Alice Applicant',
                'DateOfBirth' => '1998-08-20',
                'Email' => 'alice@applicant.test',
                'Address' => 'P.O. Box 3',
                'PhoneNumber' => '0744444444',
                'Gender' => 'Female',
                'LetterOfApplication' => 'letter.pdf',
                'HighestLevelCertificate' => 'degree.pdf',
                'CV' => 'cv.pdf',
                'ApplicationStatus' => 'Pending',
                'GoodConduct' => 'gc.pdf',
                'NationalID' => 'APP-300',
                'RecruitmentID' => $context['recruitment']->RecruitmentID,
            ],
            'employees' => [
                'FullName' => 'Mary Recruit',
                'DateOfBirth' => '1997-07-07',
                'Email' => 'mary.recruit@example.com',
                'PostalAddress' => 'P.O. Box 4',
                'PhoneNumber' => '0755555555',
                'Gender' => 'Female',
                'JobTitle' => 'Accountant',
                'NationalID' => 'EMP-301',
                'HireDate' => '2026-04-20',
                'EmploymentStatus' => 'Active',
                'DepartmentID' => $context['department']->DepartmentID,
                'SupervisorID' => $context['supervisor']->EmployeeID,
                'BranchID' => $context['branch']->BranchID,
            ],
            'user-accounts' => [
                'EmployeeID' => null,
                'Username' => 'payroll.user',
                'PasswordHash' => 'secret1234',
                'RoleID' => $context['role']->RoleID,
                'AccountStatus' => 'active',
            ],
            'notifications' => [
                'RecipientUserID' => $context['user']->UserID,
                'Title' => 'Leave Request',
                'Message' => 'A leave request needs review',
                'NotificationType' => 'leave',
                'ReferenceTable' => 'LeaveRequest',
                'ReferenceID' => 1,
                'IsRead' => false,
            ],
            'attendance' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'AttendanceDate' => '2026-04-21',
                'Time_In' => '08:00:00',
                'Time_Out' => '17:00:00',
                'AttendanceStatus' => 'Present',
            ],
            'leave-requests' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'LeaveType' => 'Annual',
                'StartDate' => '2026-05-10',
                'EndDate' => '2026-05-15',
                'Reason' => 'Vacation',
                'LeaveStatus' => 'Pending',
                'ApprovedBy' => $context['supervisor']->EmployeeID,
            ],
            'leave-balances' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'LeaveType' => 'Annual',
                'TotalDays' => 21,
                'UsedDays' => 5,
            ],
            'additional-documents' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'DocumentTypeID' => $context['documentType']->DocumentTypeID,
                'Document' => 'hrms-documents/manual-entry.pdf',
                'Description' => 'Stored document',
                'ExpiryDate' => '2027-01-01',
                'UploadedBy' => $context['employee']->EmployeeID,
            ],
            'deployment-history' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'BranchID' => $context['branch']->BranchID,
                'DeploymentSite' => 'Site A',
                'StartDate' => '2026-06-01',
                'EndDate' => '2026-06-30',
                'Reason' => 'Temporary assignment',
                'DeployedBy' => $context['supervisor']->EmployeeID,
            ],
            'employee-trainings' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'TrainingID' => $context['training']->TrainingID,
                'CompletionStatus' => 'Completed',
            ],
            'performance-evaluations' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'EvaluatorID' => $context['supervisor']->EmployeeID,
                'EvaluationPeriod' => 'Q2-2026',
                'Score' => 88,
                'Comments' => 'Strong performance',
            ],
            'payrolls' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'PayPeriod' => '2026-05',
                'BasicSalary' => 62000,
                'NetSalary' => 57000,
                'PaymentDate' => '2026-05-31',
            ],
            'assigned-allowances' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'AllowanceID' => $context['allowance']->AllowanceID,
                'EffectiveDate' => '2026-05-01',
                'EndDate' => '2026-12-31',
                'IsTaxable' => true,
                'Amount' => 5000,
            ],
            'payroll-allowances' => [
                'PayrollID' => $context['payroll']->PayrollID,
                'AssignedAllowanceID' => $this->createAssignedAllowance($context),
            ],
            'assigned-deductions' => [
                'EmployeeID' => $context['employee']->EmployeeID,
                'DeductionID' => $context['deduction']->DeductionID,
                'EffectiveDate' => '2026-05-01',
                'EndDate' => '2026-12-31',
                'Amount' => 3200,
            ],
            'payroll-deductions' => [
                'PayrollID' => $context['payroll']->PayrollID,
                'AssignedDeductionID' => $this->createAssignedDeduction($context),
            ],
        };
    }

    private function updatePayloadFor(string $resource, array $context, array $storePayload): array
    {
        return match ($resource) {
            'roles' => ['RoleName' => 'HR Manager', 'RoleDescription' => 'Updated'],
            'permissions' => ['PermissionName' => 'edit-payroll', 'Description' => 'Updated'],
            'role-permissions' => $storePayload,
            'branches' => ['BranchName' => 'Kisumu West', 'BranchLocation' => 'Kisumu CBD'],
            'document-types' => ['TypeName' => 'Certificate', 'TypeDescription' => 'Updated'],
            'allowances' => ['AllowanceName' => 'Medical'],
            'deductions' => ['DeductionName' => 'Insurance', 'Rate' => 0.04],
            'trainings' => ['TrainingName' => 'Advanced First Aid', 'TrainingType' => 'Updated', 'StartDate' => '2026-05-01', 'EndDate' => '2026-05-04'],
            'departments' => ['DepartmentName' => 'Corporate Finance', 'HODID' => $context['supervisor']->EmployeeID, 'BranchID' => $context['branch']->BranchID],
            'recruitments' => ['JobTitle' => 'Senior HR Assistant', 'DepartmentID' => $context['department']->DepartmentID, 'VacancyStatus' => 'Closed'],
            'applicants' => ['FullName' => 'Alice Applicant Updated', 'NationalID' => 'APP-300', 'RecruitmentID' => $context['recruitment']->RecruitmentID],
            'employees' => ['FullName' => 'Mary Recruit Updated', 'Email' => 'mary.recruit@example.com', 'NationalID' => 'EMP-301', 'HireDate' => '2026-04-20', 'DepartmentID' => $context['department']->DepartmentID, 'BranchID' => $context['branch']->BranchID],
            'user-accounts' => ['EmployeeID' => null, 'Username' => 'payroll.user', 'PasswordHash' => 'changedpass1', 'RoleID' => $context['role']->RoleID, 'AccountStatus' => 'suspended'],
            'notifications' => ['RecipientUserID' => $context['user']->UserID, 'Title' => 'Updated Notification', 'Message' => 'Updated body', 'IsRead' => true],
            'attendance' => ['EmployeeID' => $context['employee']->EmployeeID, 'AttendanceDate' => '2026-04-21', 'Time_In' => '08:15:00', 'Time_Out' => '17:15:00', 'AttendanceStatus' => 'Late'],
            'leave-requests' => ['EmployeeID' => $context['employee']->EmployeeID, 'LeaveType' => 'Annual', 'StartDate' => '2026-05-10', 'EndDate' => '2026-05-16', 'LeaveStatus' => 'Approved', 'ApprovedBy' => $context['supervisor']->EmployeeID],
            'leave-balances' => ['EmployeeID' => $context['employee']->EmployeeID, 'LeaveType' => 'Annual', 'TotalDays' => 21, 'UsedDays' => 7],
            'additional-documents' => ['EmployeeID' => $context['employee']->EmployeeID, 'DocumentTypeID' => $context['documentType']->DocumentTypeID, 'Document' => 'hrms-documents/updated.pdf', 'UploadedBy' => $context['employee']->EmployeeID],
            'deployment-history' => ['EmployeeID' => $context['employee']->EmployeeID, 'BranchID' => $context['branch']->BranchID, 'DeploymentSite' => 'Site B', 'StartDate' => '2026-06-01', 'EndDate' => '2026-07-05', 'DeployedBy' => $context['supervisor']->EmployeeID],
            'employee-trainings' => ['EmployeeID' => $context['employee']->EmployeeID, 'TrainingID' => $context['training']->TrainingID, 'CompletionStatus' => 'In Progress'],
            'performance-evaluations' => ['EmployeeID' => $context['employee']->EmployeeID, 'EvaluatorID' => $context['supervisor']->EmployeeID, 'EvaluationPeriod' => 'Q2-2026', 'Score' => 92, 'Comments' => 'Improved'],
            'payrolls' => ['EmployeeID' => $context['employee']->EmployeeID, 'PayPeriod' => '2026-05', 'BasicSalary' => 62000, 'NetSalary' => 59000, 'PaymentDate' => '2026-05-31'],
            'assigned-allowances' => ['EmployeeID' => $context['employee']->EmployeeID, 'AllowanceID' => $context['allowance']->AllowanceID, 'EffectiveDate' => '2026-05-01', 'EndDate' => '2026-12-31', 'IsTaxable' => false, 'Amount' => 6500],
            'payroll-allowances' => $storePayload,
            'assigned-deductions' => ['EmployeeID' => $context['employee']->EmployeeID, 'DeductionID' => $context['deduction']->DeductionID, 'EffectiveDate' => '2026-05-01', 'EndDate' => '2026-12-31', 'Amount' => 2800],
            'payroll-deductions' => $storePayload,
        };
    }

    private function createAssignedAllowance(array $context): int
    {
        return $this->postJson('/api/assigned-allowances', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'AllowanceID' => $context['allowance']->AllowanceID,
            'EffectiveDate' => '2026-04-01',
            'Amount' => 2000,
        ])->json('AssignedAllowanceID');
    }

    private function createAssignedDeduction(array $context): int
    {
        return $this->postJson('/api/assigned-deductions', [
            'EmployeeID' => $context['employee']->EmployeeID,
            'DeductionID' => $context['deduction']->DeductionID,
            'EffectiveDate' => '2026-04-01',
            'Amount' => 1500,
        ])->json('AssignedDeductionID');
    }

    private function extractId(string $resource, array $record): int
    {
        $key = [
            'roles' => 'RoleID',
            'permissions' => 'PermissionID',
            'role-permissions' => 'RolePermissionID',
            'branches' => 'BranchID',
            'document-types' => 'DocumentTypeID',
            'allowances' => 'AllowanceID',
            'deductions' => 'DeductionID',
            'trainings' => 'TrainingID',
            'departments' => 'DepartmentID',
            'recruitments' => 'RecruitmentID',
            'applicants' => 'ApplicationID',
            'employees' => 'EmployeeID',
            'user-accounts' => 'UserID',
            'notifications' => 'NotificationID',
            'attendance' => 'AttendanceID',
            'leave-requests' => 'LeaveID',
            'leave-balances' => 'LeaveBalanceID',
            'additional-documents' => 'DocumentID',
            'deployment-history' => 'DeploymentID',
            'employee-trainings' => 'EmployeeTrainingID',
            'performance-evaluations' => 'EvaluationID',
            'payrolls' => 'PayrollID',
            'assigned-allowances' => 'AssignedAllowanceID',
            'payroll-allowances' => 'PayrollAllowanceID',
            'assigned-deductions' => 'AssignedDeductionID',
            'payroll-deductions' => 'PayrollDeductionID',
        ][$resource] ?? null;

        if ($key !== null && isset($record[$key]) && is_int($record[$key])) {
            return $record[$key];
        }

        $this->fail('No primary key was found in the API response.');
    }

    private function configureB2Disk(): void
    {
        config()->set('filesystems.default', 'b2');
        config()->set('filesystems.disks.b2.key', 'key-id');
        config()->set('filesystems.disks.b2.secret', 'app-key');
        config()->set('filesystems.disks.b2.bucket', 'hrms-documents');
        config()->set('filesystems.disks.b2.endpoint', 'https://s3.us-west-002.backblazeb2.com');
        config()->set('filesystems.disks.b2.url', 'https://f000.backblazeb2.com/file/hrms-documents');
    }
}
