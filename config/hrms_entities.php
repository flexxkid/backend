<?php

use App\Models\AdditionalDocuments;
use App\Models\Allowances;
use App\Models\Applicant;
use App\Models\AssignedAllowance;
use App\Models\AssignedDeduction;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Deductions;
use App\Models\DeploymentHistory;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Notifications;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use App\Models\PayrollDeduction;
use App\Models\PerformanceEvaluation;
use App\Models\Permission;
use App\Models\Recruitment;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Training;
use App\Models\UserAccount;

return [
    'branches' => [
        'model' => Branch::class,
        'rules' => [
            'BranchName' => ['required', 'string', 'max:150', 'unique:Branch,BranchName,{id},BranchID'],
            'BranchLocation' => ['nullable', 'string', 'max:255'],
            'BranchPhone' => ['nullable', 'string', 'max:20'],
            'BranchEmail' => ['nullable', 'email', 'max:150'],
        ],
        'includes' => ['employees', 'departments', 'deploymentHistories'],
    ],
    'roles' => [
        'model' => Role::class,
        'rules' => [
            'RoleName' => ['required', 'string', 'max:100'],
            'RoleDescription' => ['nullable', 'string'],
        ],
        'includes' => ['userAccounts', 'permissions'],
    ],
    'permissions' => [
        'model' => Permission::class,
        'rules' => [
            'PermissionName' => ['required', 'string', 'max:100', 'unique:Permission,PermissionName,{id},PermissionID'],
            'Description' => ['nullable', 'string'],
        ],
        'includes' => ['roles'],
    ],
    'role-permissions' => [
        'model' => RolePermission::class,
        'rules' => [
            'RoleID' => ['required', 'integer', 'exists:Role,RoleID'],
            'PermissionID' => ['required', 'integer', 'exists:Permission,PermissionID'],
        ],
        'includes' => ['role', 'permission'],
    ],
    'document-types' => [
        'model' => DocumentType::class,
        'rules' => [
            'TypeName' => ['required', 'string', 'max:100'],
            'TypeDescription' => ['nullable', 'string'],
        ],
        'includes' => ['documents'],
    ],
    'allowances' => [
        'model' => Allowances::class,
        'rules' => [
            'AllowanceName' => ['required', 'string', 'max:150'],
        ],
        'includes' => ['assignedAllowances'],
    ],
    'deductions' => [
        'model' => Deductions::class,
        'rules' => [
            'DeductionName' => ['required', 'string', 'max:150'],
            'Rate' => ['nullable', 'numeric'],
        ],
        'includes' => ['assignedDeductions'],
    ],
    'trainings' => [
        'model' => Training::class,
        'rules' => [
            'TrainingName' => ['required', 'string', 'max:200'],
            'TrainingType' => ['nullable', 'string', 'max:100'],
            'StartDate' => ['nullable', 'date'],
            'EndDate' => ['nullable', 'date', 'after_or_equal:StartDate'],
        ],
        'includes' => ['employees'],
    ],
    'departments' => [
        'model' => Department::class,
        'rules' => [
            'DepartmentName' => ['required', 'string', 'max:150'],
            'HODID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'BranchID' => ['nullable', 'integer', 'exists:Branch,BranchID'],
            'DepartmentDescription' => ['nullable', 'string'],
            'CreatedDate' => ['nullable', 'date'],
        ],
        'includes' => ['headOfDepartment', 'branch', 'employees', 'recruitments'],
    ],
    'recruitments' => [
        'model' => Recruitment::class,
        'rules' => [
            'JobTitle' => ['required', 'string', 'max:150'],
            'DepartmentID' => ['nullable', 'integer', 'exists:Department,DepartmentID'],
            'VacancyStatus' => ['nullable', 'string', 'max:50'],
            'PostedDate' => ['nullable', 'date'],
        ],
        'includes' => ['department', 'applicants'],
    ],
    'applicants' => [
        'model' => Applicant::class,
        'rules' => [
            'FullName' => ['required', 'string', 'max:200'],
            'DateOfBirth' => ['nullable', 'date'],
            'Email' => ['nullable', 'email', 'max:150'],
            'Address' => ['nullable', 'string', 'max:255'],
            'PhoneNumber' => ['nullable', 'string', 'max:20'],
            'Gender' => ['nullable', 'string', 'max:20'],
            'LetterOfApplication' => ['nullable', 'string', 'max:500'],
            'HighestLevelCertificate' => ['nullable', 'string', 'max:255'],
            'CV' => ['nullable', 'string', 'max:500'],
            'ApplicationStatus' => ['nullable', 'string', 'max:50'],
            'GoodConduct' => ['nullable', 'string', 'max:500'],
            'NationalID' => ['required', 'string', 'max:50', 'unique:Applicant,NationalID,{id},ApplicationID'],
            'RecruitmentID' => ['nullable', 'integer', 'exists:Recruitment,RecruitmentID'],
        ],
        'includes' => ['recruitment'],
    ],
    'employees' => [
        'model' => Employee::class,
        'rules' => [
            'FullName' => ['required', 'string', 'max:200'],
            'DateOfBirth' => ['nullable', 'date'],
            'Email' => ['required', 'email', 'max:150', 'unique:Employee,Email,{id},EmployeeID'],
            'PostalAddress' => ['nullable', 'string', 'max:255'],
            'PhoneNumber' => ['nullable', 'string', 'max:20'],
            'Gender' => ['nullable', 'string', 'max:20'],
            'JobTitle' => ['nullable', 'string', 'max:150'],
            'LetterOfApplication' => ['nullable', 'string', 'max:500'],
            'HighestLevelCertificate' => ['nullable', 'string', 'max:255'],
            'CV' => ['nullable', 'string', 'max:500'],
            'ApplicationStatus' => ['nullable', 'string', 'max:50'],
            'GoodConduct' => ['nullable', 'string', 'max:500'],
            'NationalID' => ['required', 'string', 'max:50', 'unique:Employee,NationalID,{id},EmployeeID'],
            'HireDate' => ['required', 'date'],
            'EmploymentStatus' => ['nullable', 'string', 'max:50'],
            'DepartmentID' => ['nullable', 'integer', 'exists:Department,DepartmentID'],
            'SupervisorID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'BranchID' => ['nullable', 'integer', 'exists:Branch,BranchID'],
        ],
        'includes' => ['department', 'branch', 'supervisor', 'userAccount', 'subordinates'],
    ],
    'user-accounts' => [
        'model' => UserAccount::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'Username' => ['required', 'string', 'max:100', 'unique:UserAccount,Username,{id},UserID'],
            'PasswordHash' => ['required', 'string', 'min:8'],
            'RoleID' => ['nullable', 'integer', 'exists:Role,RoleID'],
            'AccountStatus' => ['nullable', 'string', 'max:50'],
            'LastLogin' => ['nullable', 'date'],
        ],
        'includes' => ['employee', 'role', 'notifications'],
    ],
    'notifications' => [
        'model' => Notifications::class,
        'rules' => [
            'RecipientUserID' => ['nullable', 'integer', 'exists:UserAccount,UserID'],
            'Title' => ['required', 'string', 'max:200'],
            'Message' => ['required', 'string'],
            'NotificationType' => ['nullable', 'string', 'max:100'],
            'ReferenceTable' => ['nullable', 'string', 'max:100'],
            'ReferenceID' => ['nullable', 'integer'],
            'IsRead' => ['nullable', 'boolean'],
            'CreatedAt' => ['nullable', 'date'],
            'ReadAt' => ['nullable', 'date'],
        ],
        'includes' => ['recipient'],
    ],
    'attendance' => [
        'model' => Attendance::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'AttendanceDate' => ['required', 'date'],
            'Time_In' => ['nullable', 'date_format:H:i:s'],
            'Time_Out' => ['nullable', 'date_format:H:i:s'],
            'AttendanceStatus' => ['nullable', 'string', 'max:50'],
        ],
        'includes' => ['employee'],
    ],
    'leave-requests' => [
        'model' => LeaveRequest::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'LeaveType' => ['required', 'string', 'max:100'],
            'StartDate' => ['required', 'date'],
            'EndDate' => ['required', 'date', 'after_or_equal:StartDate'],
            'Reason' => ['nullable', 'string'],
            'LeaveStatus' => ['nullable', 'string', 'max:50'],
            'ApprovedBy' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'ApprovedAt' => ['nullable', 'date'],
        ],
        'includes' => ['employee', 'approvedBy'],
    ],
    'leave-balances' => [
        'model' => LeaveBalance::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'LeaveType' => ['required', 'string', 'max:100'],
            'TotalDays' => ['required', 'integer', 'min:0'],
            'UsedDays' => ['nullable', 'integer', 'min:0'],
            'RemainingDays' => ['nullable', 'integer', 'min:0'],
        ],
        'includes' => ['employee'],
    ],
    'additional-documents' => [
        'model' => AdditionalDocuments::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'DocumentTypeID' => ['nullable', 'integer', 'exists:DocumentType,DocumentTypeID'],
            'Document' => ['required', 'string', 'max:500'],
            'Description' => ['nullable', 'string'],
            'ExpiryDate' => ['nullable', 'date'],
            'UploadDate' => ['nullable', 'date'],
            'UploadedBy' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
        ],
        'includes' => ['employee', 'documentType', 'uploadedBy'],
    ],
    'deployment-history' => [
        'model' => DeploymentHistory::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'BranchID' => ['nullable', 'integer', 'exists:Branch,BranchID'],
            'DeploymentSite' => ['nullable', 'string', 'max:255'],
            'StartDate' => ['required', 'date'],
            'EndDate' => ['nullable', 'date', 'after_or_equal:StartDate'],
            'Reason' => ['nullable', 'string'],
            'DeployedBy' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
        ],
        'includes' => ['employee', 'branch', 'deployedBy'],
    ],
    'employee-trainings' => [
        'model' => EmployeeTraining::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'TrainingID' => ['nullable', 'integer', 'exists:Training,TrainingID'],
            'CompletionStatus' => ['nullable', 'string', 'max:50'],
        ],
        'includes' => ['employee', 'training'],
    ],
    'performance-evaluations' => [
        'model' => PerformanceEvaluation::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'EvaluatorID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'EvaluationPeriod' => ['nullable', 'string', 'max:100'],
            'Score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'Comments' => ['nullable', 'string'],
        ],
        'includes' => ['employee', 'evaluator'],
    ],
    'payrolls' => [
        'model' => Payroll::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'PayPeriod' => ['nullable', 'string', 'max:100'],
            'BasicSalary' => ['nullable', 'numeric', 'min:0'],
            'NetSalary' => ['nullable', 'numeric', 'min:0'],
            'PaymentDate' => ['nullable', 'date'],
        ],
        'includes' => ['employee', 'allowances', 'deductions'],
    ],
    'assigned-allowances' => [
        'model' => AssignedAllowance::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'AllowanceID' => ['nullable', 'integer', 'exists:Allowances,AllowanceID'],
            'EffectiveDate' => ['nullable', 'date'],
            'EndDate' => ['nullable', 'date', 'after_or_equal:EffectiveDate'],
            'IsTaxable' => ['nullable', 'boolean'],
            'Amount' => ['nullable', 'numeric', 'min:0'],
        ],
        'includes' => ['employee', 'allowance', 'payrolls'],
    ],
    'payroll-allowances' => [
        'model' => PayrollAllowance::class,
        'rules' => [
            'PayrollID' => ['required', 'integer', 'exists:Payroll,PayrollID'],
            'AssignedAllowanceID' => ['required', 'integer', 'exists:AssignedAllowance,AssignedAllowanceID'],
        ],
        'includes' => ['payroll', 'assignedAllowance'],
    ],
    'assigned-deductions' => [
        'model' => AssignedDeduction::class,
        'rules' => [
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'DeductionID' => ['nullable', 'integer', 'exists:Deductions,DeductionID'],
            'EffectiveDate' => ['nullable', 'date'],
            'EndDate' => ['nullable', 'date', 'after_or_equal:EffectiveDate'],
            'Amount' => ['nullable', 'numeric', 'min:0'],
        ],
        'includes' => ['employee', 'deduction', 'payrolls'],
    ],
    'payroll-deductions' => [
        'model' => PayrollDeduction::class,
        'rules' => [
            'PayrollID' => ['required', 'integer', 'exists:Payroll,PayrollID'],
            'AssignedDeductionID' => ['required', 'integer', 'exists:AssignedDeduction,AssignedDeductionID'],
        ],
        'includes' => ['payroll', 'assignedDeduction'],
    ],
    'audit-logs' => [
        'model' => AuditLog::class,
        'rules' => [
            'UserID' => ['nullable', 'integer'],
            'Username' => ['nullable', 'string', 'max:100'],
            'Action' => ['required', 'string', 'max:100'],
            'AffectedTable' => ['required', 'string', 'max:100'],
            'AffectedRecordID' => ['nullable', 'integer'],
            'OldValues' => ['nullable', 'array'],
            'NewValues' => ['nullable', 'array'],
            'IPAddress' => ['nullable', 'ip'],
            'CreatedAt' => ['nullable', 'date'],
        ],
        'readonly' => true,
    ],
];
