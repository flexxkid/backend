<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DeploymentHistory;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\PerformanceEvaluation;
use App\Models\Role;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrmsComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_only_sees_employees_in_their_branch(): void
    {
        $context = $this->seedEmployeeAccessContext();

        Sanctum::actingAs($context['managerAccount']);

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.EmployeeID', $context['branchEmployee']->EmployeeID);

        $this->getJson("/api/employees/{$context['otherBranchEmployee']->EmployeeID}")
            ->assertForbidden();
    }

    public function test_employee_destroy_deactivates_profile_without_deleting_it(): void
    {
        $context = $this->seedEmployeeAccessContext();

        $linkedAccount = UserAccount::create([
            'EmployeeID' => $context['branchEmployee']->EmployeeID,
            'Username' => 'john.kamau',
            'PasswordHash' => Hash::make('secret123'),
            'RoleID' => $context['managerAccount']->RoleID,
            'AccountStatus' => 'active',
        ]);
        $linkedAccount->createToken('employee-session');

        Sanctum::actingAs($context['adminAccount']);

        $this->deleteJson("/api/employees/{$context['branchEmployee']->EmployeeID}")
            ->assertOk()
            ->assertJsonPath('message', 'Employee deactivated');

        $this->assertDatabaseHas('Employee', [
            'EmployeeID' => $context['branchEmployee']->EmployeeID,
            'EmploymentStatus' => 'Inactive',
        ]);
        $this->assertDatabaseHas('UserAccount', [
            'UserID' => $linkedAccount->UserID,
            'AccountStatus' => 'inactive',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_employee_show_includes_deployment_and_performance_history_payloads(): void
    {
        $context = $this->seedEmployeeAccessContext();

        DeploymentHistory::create([
            'EmployeeID' => $context['branchEmployee']->EmployeeID,
            'BranchID' => $context['mainBranch']->BranchID,
            'DeploymentSite' => 'HQ Gate',
            'StartDate' => '2026-05-01',
            'EndDate' => '2026-05-31',
            'Reason' => 'Monthly rotation',
            'DeployedBy' => $context['branchManagerEmployee']->EmployeeID,
        ]);

        PerformanceEvaluation::create([
            'EmployeeID' => $context['branchEmployee']->EmployeeID,
            'EvaluatorID' => $context['adminEmployee']->EmployeeID,
            'EvaluationPeriod' => 'Q2-2026',
            'Score' => 91,
            'Comments' => 'Reliable coverage at assigned post.',
        ]);

        Sanctum::actingAs($context['adminAccount']);

        $this->getJson("/api/employees/{$context['branchEmployee']->EmployeeID}")
            ->assertOk()
            ->assertJsonPath('deployment_histories.0.DeploymentSite', 'HQ Gate')
            ->assertJsonPath('deployment_histories.0.branch.BranchName', 'Nairobi HQ')
            ->assertJsonPath('performance_evaluations.0.EvaluationPeriod', 'Q2-2026')
            ->assertJsonPath('performance_evaluations.0.evaluator.FullName', 'Admin User');
    }

    public function test_employee_search_is_case_insensitive_and_supports_csv_and_pdf_exports(): void
    {
        $context = $this->seedEmployeeAccessContext();

        Sanctum::actingAs($context['adminAccount']);

        $this->getJson('/api/employees?search=kam')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.FullName', 'John Kamau');

        $this->get('/api/employees?search=kam&export=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $pdfResponse = $this->get('/api/employees?search=kam&export=pdf');

        $pdfResponse->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }

    public function test_leave_approval_records_approver_and_timestamp(): void
    {
        $context = $this->seedEmployeeAccessContext();

        Sanctum::actingAs($context['adminAccount']);

        LeaveBalance::create([
            'EmployeeID' => $context['branchEmployee']->EmployeeID,
            'LeaveType' => 'Annual',
            'TotalDays' => 21,
            'UsedDays' => 0,
            'RemainingDays' => 21,
        ]);

        $leave = $this->postJson('/api/leave', [
            'EmployeeID' => $context['branchEmployee']->EmployeeID,
            'LeaveType' => 'Annual',
            'StartDate' => '2026-05-01',
            'EndDate' => '2026-05-03',
            'Reason' => 'Vacation',
        ])->assertCreated()->json();

        $this->patchJson("/api/leave/{$leave['LeaveID']}/approve", [
            'status' => 'Approved',
        ])->assertOk()
            ->assertJsonPath('ApprovedBy', $context['adminEmployee']->EmployeeID)
            ->assertJsonPath('LeaveStatus', 'Approved');

        $this->assertDatabaseHas('LeaveRequest', [
            'LeaveID' => $leave['LeaveID'],
            'ApprovedBy' => $context['adminEmployee']->EmployeeID,
        ]);

        $this->assertNotNull(
            \App\Models\LeaveRequest::findOrFail($leave['LeaveID'])->ApprovedAt
        );
    }

    private function seedEmployeeAccessContext(): array
    {
        $adminRole = Role::create([
            'RoleName' => 'HR Administrator',
            'RoleDescription' => 'Admin',
        ]);

        $managerRole = Role::create([
            'RoleName' => 'Branch Manager',
            'RoleDescription' => 'Manager',
        ]);

        $mainBranch = Branch::create([
            'BranchName' => 'Nairobi HQ',
            'BranchLocation' => 'Nairobi',
        ]);

        $otherBranch = Branch::create([
            'BranchName' => 'Mombasa',
            'BranchLocation' => 'Mombasa',
        ]);

        $mainDepartment = Department::create([
            'DepartmentName' => 'Operations',
            'BranchID' => $mainBranch->BranchID,
            'CreatedDate' => '2026-01-01',
        ]);

        $otherDepartment = Department::create([
            'DepartmentName' => 'Field',
            'BranchID' => $otherBranch->BranchID,
            'CreatedDate' => '2026-01-01',
        ]);

        $adminEmployee = Employee::create([
            'FullName' => 'Admin User',
            'DateOfBirth' => '1989-01-01',
            'Email' => 'admin@example.com',
            'NationalID' => 'ADM-001',
            'HireDate' => '2024-01-01',
            'EmploymentStatus' => 'Active',
            'DepartmentID' => $otherDepartment->DepartmentID,
            'BranchID' => $otherBranch->BranchID,
        ]);

        $branchManagerEmployee = Employee::create([
            'FullName' => 'Manager User',
            'DateOfBirth' => '1990-01-01',
            'Email' => 'manager@example.com',
            'NationalID' => 'MGR-001',
            'HireDate' => '2024-01-01',
            'EmploymentStatus' => 'Active',
            'DepartmentID' => $mainDepartment->DepartmentID,
            'BranchID' => $mainBranch->BranchID,
        ]);

        $branchEmployee = Employee::create([
            'FullName' => 'John Kamau',
            'DateOfBirth' => '1995-02-01',
            'Email' => 'john.kamau@example.com',
            'NationalID' => 'EMP-001',
            'JobTitle' => 'Guard',
            'HireDate' => '2025-01-10',
            'EmploymentStatus' => 'Active',
            'DepartmentID' => $mainDepartment->DepartmentID,
            'SupervisorID' => $branchManagerEmployee->EmployeeID,
            'BranchID' => $mainBranch->BranchID,
        ]);

        $otherBranchEmployee = Employee::create([
            'FullName' => 'Mary Otieno',
            'DateOfBirth' => '1994-03-01',
            'Email' => 'mary.otieno@example.com',
            'NationalID' => 'EMP-002',
            'JobTitle' => 'Guard',
            'HireDate' => '2025-02-10',
            'EmploymentStatus' => 'Active',
            'DepartmentID' => $otherDepartment->DepartmentID,
            'BranchID' => $otherBranch->BranchID,
        ]);

        $adminAccount = UserAccount::create([
            'EmployeeID' => $adminEmployee->EmployeeID,
            'Username' => 'admin.account',
            'PasswordHash' => Hash::make('secret123'),
            'RoleID' => $adminRole->RoleID,
            'AccountStatus' => 'active',
        ]);

        $managerAccount = UserAccount::create([
            'EmployeeID' => $branchManagerEmployee->EmployeeID,
            'Username' => 'manager.account',
            'PasswordHash' => Hash::make('secret123'),
            'RoleID' => $managerRole->RoleID,
            'AccountStatus' => 'active',
        ]);

        return compact(
            'adminEmployee',
            'branchManagerEmployee',
            'branchEmployee',
            'otherBranchEmployee',
            'adminAccount',
            'managerAccount',
            'mainBranch',
            'otherBranch',
            'mainDepartment',
            'otherDepartment',
        );
    }
}
