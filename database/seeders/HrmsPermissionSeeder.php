<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\UserAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HrmsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'HR Administrator' => [
                'manage-users',
                'manage-roles',
                'manage-employees',
                'manage-documents',
                'manage-leave',
                'approve-leave',
                'manage-attendance',
                'manage-payroll',
                'manage-training',
                'manage-recruitment',
                'manage-deployments',
                'view-audit-logs',
                'manage-notifications',
                'manage-allowances',
                'manage-deductions',
            ],
            'Branch Manager' => [
                'view-employees',
                'view-documents',
                'manage-leave',
                'approve-leave',
                'manage-attendance',
                'view-performance',
                'view-training',
                'manage-deployments',
                'view-notifications',
            ],
            'HR Officer' => [
                'view-employees',
                'manage-employees',
                'manage-documents',
                'manage-leave',
                'manage-attendance',
                'manage-training',
                'view-recruitment',
                'view-performance',
                'view-notifications',
            ],
            'Auditor' => [
                'view-employees',
                'view-documents',
                'view-leave',
                'view-attendance',
                'view-training',
                'view-performance',
                'view-audit-logs',
                'view-notifications',
            ],
            'Employee' => [
                'view-self-profile',
                'submit-leave',
                'view-self-performance',
                'view-notifications',
            ],
        ];

        $permissionDescriptions = [
            'manage-users' => 'Create, update, deactivate and reset user accounts.',
            'manage-roles' => 'Manage roles and role assignments.',
            'manage-employees' => 'Create and update employee records.',
            'manage-documents' => 'Upload and manage employee documents.',
            'manage-leave' => 'Create and review leave requests.',
            'approve-leave' => 'Approve or reject leave requests.',
            'manage-attendance' => 'Create and update attendance records.',
            'manage-payroll' => 'Process and view payroll.',
            'manage-training' => 'Manage training catalogues and enrolments.',
            'manage-recruitment' => 'Create vacancies and manage applicants.',
            'manage-deployments' => 'Manage deployment history.',
            'view-audit-logs' => 'View tamper-evident audit logs.',
            'manage-notifications' => 'Create and manage notifications.',
            'manage-allowances' => 'Manage allowance definitions and assignments.',
            'manage-deductions' => 'Manage deduction definitions and assignments.',
            'view-employees' => 'View employee records.',
            'view-documents' => 'View employee documents.',
            'view-leave' => 'View leave requests and balances.',
            'view-attendance' => 'View attendance records.',
            'view-training' => 'View training records.',
            'view-recruitment' => 'View recruitment postings.',
            'view-performance' => 'View performance evaluations.',
            'view-notifications' => 'View notifications.',
            'view-self-profile' => 'View own employee profile.',
            'submit-leave' => 'Submit leave requests.',
            'view-self-performance' => 'View own performance evaluations.',
        ];

        $roles = [];

        foreach (array_keys($rolePermissions) as $roleName) {
            $roles[$roleName] = Role::query()->updateOrCreate(
                ['RoleName' => $roleName],
                ['RoleDescription' => $roleName.' role']
            );
        }

        $permissions = [];

        foreach ($permissionDescriptions as $name => $description) {
            $permissions[$name] = Permission::query()->updateOrCreate(
                ['PermissionName' => $name],
                ['Description' => $description]
            );
        }

        foreach ($rolePermissions as $roleName => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                RolePermission::query()->firstOrCreate([
                    'RoleID' => $roles[$roleName]->RoleID,
                    'PermissionID' => $permissions[$permissionName]->PermissionID,
                ]);
            }
        }

        $branch = Branch::query()->updateOrCreate(
            ['BranchName' => 'Seed HQ'],
            [
                'BranchLocation' => 'Nairobi',
                'BranchPhone' => '0700000000',
                'BranchEmail' => 'seed-hq@example.com',
            ]
        );

        $department = Department::query()->updateOrCreate(
            ['DepartmentName' => 'Seed Administration'],
            [
                'BranchID' => $branch->BranchID,
                'DepartmentDescription' => 'Bootstrap administration department',
                'CreatedDate' => now()->toDateString(),
            ]
        );

        $employee = Employee::query()->updateOrCreate(
            ['NationalID' => 'SEED-ADMIN-001'],
            [
                'FullName' => 'Seed Admin',
                'DateOfBirth' => '1990-01-01',
                'Email' => 'seed.admin@example.com',
                'PostalAddress' => 'P.O. Box 100',
                'PhoneNumber' => '0712345678',
                'Gender' => 'Female',
                'JobTitle' => 'HR Administrator',
                'HireDate' => now()->toDateString(),
                'EmploymentStatus' => 'Active',
                'DepartmentID' => $department->DepartmentID,
                'BranchID' => $branch->BranchID,
            ]
        );

        UserAccount::query()->updateOrCreate(
            ['Username' => 'seed.admin'],
            [
                'EmployeeID' => $employee->EmployeeID,
                'PasswordHash' => Hash::make('secret123'),
                'RoleID' => $roles['HR Administrator']->RoleID,
                'AccountStatus' => 'active',
            ]
        );
    }
}
