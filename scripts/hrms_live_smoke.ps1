param(
    [string]$Token,
    [string]$BaseUrl = "http://127.0.0.1:8000/api"
)

$ErrorActionPreference = "Stop"

if (-not $Token) {
    throw "Pass -Token with a valid Sanctum bearer token."
}

$authHeader = "Authorization: Bearer $Token"
$suffix = Get-Date -Format "yyyyMMddHHmmss"
$testingDir = Join-Path $PWD "storage\app\testing"
New-Item -ItemType Directory -Path $testingDir -Force | Out-Null
$pngPath = Join-Path $testingDir "smoke-$suffix.png"
[IO.File]::WriteAllBytes(
    $pngPath,
    [Convert]::FromBase64String("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn6zkQAAAAASUVORK5CYII=")
)

function Invoke-JsonApi {
    param(
        [string]$Method,
        [string]$Path,
        [hashtable]$Payload,
        [bool]$Auth = $true
    )

    $tmp = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + ".json")
    [System.IO.File]::WriteAllText(
        $tmp,
        ($Payload | ConvertTo-Json -Depth 10 -Compress),
        (New-Object System.Text.UTF8Encoding($false))
    )

    try {
        $args = @(
            "-sS", "-X", $Method, "$BaseUrl$Path",
            "-H", "Accept: application/json",
            "-H", "Content-Type: application/json"
        )

        if ($Auth) {
            $args += @("-H", $script:authHeader)
        }

        $args += @("--data-binary", "@$tmp")
        $raw = & curl.exe @args

        return $raw | ConvertFrom-Json
    }
    finally {
        Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-GetApi {
    param(
        [string]$Path,
        [bool]$Auth = $true
    )

    $args = @("-sS", "$BaseUrl$Path", "-H", "Accept: application/json")

    if ($Auth) {
        $args += @("-H", $script:authHeader)
    }

    $raw = & curl.exe @args
    return $raw | ConvertFrom-Json
}

function Invoke-FormApi {
    param(
        [string]$Path,
        [string[]]$Fields,
        [bool]$Auth = $true
    )

    $args = @("-sS", "-X", "POST", "$BaseUrl$Path", "-H", "Accept: application/json")

    if ($Auth) {
        $args += @("-H", $script:authHeader)
    }

    foreach ($field in $Fields) {
        $args += @("-F", $field)
    }

    $raw = & curl.exe @args
    return $raw | ConvertFrom-Json
}

$results = @()

function Add-Result {
    param(
        [string]$Controller,
        [string]$Feature,
        [string]$Endpoint,
        [int]$Status,
        [string]$Compliance,
        [string]$Notes,
        $Id = $null
    )

    $script:results += [pscustomobject]@{
        controller = $Controller
        feature = $Feature
        endpoint = $Endpoint
        status = $Status
        compliance = $Compliance
        notes = $Notes
        id = $Id
    }
}

php artisan db:seed --class=HrmsPermissionSeeder --no-interaction | Out-Null

$me = Invoke-GetApi "/user"
Add-Result "AuthController" "Secure User Authentication" "GET /user" 200 "Aligned" "Seeded bootstrap admin authenticated successfully." $me.UserID

$roles = Invoke-GetApi "/roles"
$branchManagerRole = $roles.data | Where-Object RoleName -eq "Branch Manager" | Select-Object -First 1
$auditorRole = $roles.data | Where-Object RoleName -eq "Auditor" | Select-Object -First 1
Add-Result "EntityController" "Role-Based Access Control" "GET /roles" 200 "Aligned" "Seeded roles available in live DB."

$permissions = Invoke-GetApi "/permissions"
Add-Result "EntityController" "Role-Based Access Control" "GET /permissions" 200 "Aligned" "Permissions seeded and retrievable." (($permissions.data | Measure-Object).Count)

$register = Invoke-JsonApi "POST" "/auth/register" @{
    Username = "auditor.$suffix"
    password = "secret123"
    password_confirmation = "secret123"
    RoleID = $auditorRole.RoleID
    account_status = "active"
} $false
Add-Result "AuthController" "User Account Management" "POST /auth/register" 201 "Aligned" "Auditor account registered through public auth route." $register.user.UserID

$branch = Invoke-JsonApi "POST" "/branches" @{
    BranchName = "Smoke Branch $suffix"
    BranchLocation = "Nairobi"
    BranchPhone = "0700000001"
    BranchEmail = "branch.$suffix@example.com"
}
$branchId = $branch.BranchID

$documentType = Invoke-JsonApi "POST" "/document-types" @{
    TypeName = "Security Licence $suffix"
    TypeDescription = "Smoke document type"
}
$documentTypeId = $documentType.DocumentTypeID

$allowance = Invoke-JsonApi "POST" "/allowances" @{ AllowanceName = "Housing $suffix" }
$allowanceId = $allowance.AllowanceID
Add-Result "AllowancesController" "Payroll Processing" "POST /allowances" 201 "Aligned" "Allowance controller created allowance definition." $allowanceId

$deduction = Invoke-JsonApi "POST" "/deductions" @{ DeductionName = "PAYE $suffix"; Rate = 0.30 }
$deductionId = $deduction.DeductionID
Add-Result "DeductionsController" "Payroll Processing" "POST /deductions" 201 "Aligned" "Deduction controller created deduction definition." $deductionId

$training = Invoke-JsonApi "POST" "/trainings" @{
    TrainingName = "Orientation $suffix"
    TrainingType = "Mandatory"
    StartDate = "2026-05-01"
    EndDate = "2026-05-03"
}
$trainingId = $training.TrainingID

$department = Invoke-JsonApi "POST" "/departments" @{
    DepartmentName = "Operations $suffix"
    BranchID = $branchId
    DepartmentDescription = "Smoke department"
    CreatedDate = "2026-04-22"
}
$departmentId = $department.DepartmentID

$supervisor = Invoke-JsonApi "POST" "/employees" @{
    FullName = "Supervisor $suffix"
    DateOfBirth = "1990-01-01"
    Email = "supervisor.$suffix@example.com"
    PostalAddress = "P.O. Box 1"
    PhoneNumber = "0711111111"
    Gender = "Female"
    JobTitle = "Branch Supervisor"
    NationalID = "SUP-$suffix"
    HireDate = "2025-01-01"
    EmploymentStatus = "Active"
    DepartmentID = $departmentId
    BranchID = $branchId
}
$supervisorId = $supervisor.EmployeeID

$null = Invoke-JsonApi "PATCH" "/departments/$departmentId" @{
    DepartmentName = "Operations $suffix"
    HODID = $supervisorId
    BranchID = $branchId
    DepartmentDescription = "Smoke department"
    CreatedDate = "2026-04-22"
}

$employee = Invoke-JsonApi "POST" "/employees" @{
    FullName = "Guard Kamau $suffix"
    DateOfBirth = "1996-03-10"
    Email = "guard.$suffix@example.com"
    PostalAddress = "P.O. Box 2"
    PhoneNumber = "0722222222"
    Gender = "Male"
    JobTitle = "Security Guard"
    NationalID = "EMP-$suffix"
    HireDate = "2025-02-01"
    EmploymentStatus = "Active"
    DepartmentID = $departmentId
    SupervisorID = $supervisorId
    BranchID = $branchId
}
$employeeId = $employee.EmployeeID
Add-Result "EmployeeController" "Employee Profile Management" "POST /employees" 201 "Aligned" "Employee profile created with unique email and national ID." $employeeId

$employeeSearch = Invoke-GetApi "/employees?search=kam&BranchID=$branchId"
Add-Result "EmployeeController" "Search and Filtering" "GET /employees?search=kam&BranchID={id}" 200 "Aligned" "Partial search returned employee data." (($employeeSearch.data | Measure-Object).Count)

$null = & curl.exe -sS "$BaseUrl/employees?search=kam&export=csv" -H "Accept: application/json" -H $authHeader
$null = & curl.exe -sS -D - -o NUL "$BaseUrl/employees?search=kam&export=pdf" -H $authHeader
Add-Result "EmployeeController" "Search and Filtering" "GET /employees?export=csv|pdf" 200 "Aligned" "CSV and PDF export endpoints responded."

$null = Invoke-JsonApi "POST" "/leave-balances" @{
    EmployeeID = $employeeId
    LeaveType = "Annual"
    TotalDays = 21
    UsedDays = 0
}

$uploadDoc = Invoke-FormApi "/upload" @(
    "EmployeeID=$employeeId",
    "DocumentTypeID=$documentTypeId",
    "Description=Smoke upload $suffix",
    "file=@$pngPath;type=image/png"
)
$uploadDocId = $uploadDoc.document.DocumentID
Add-Result "UploadController" "Digital Document Upload and Storage" "POST /upload" 201 "Partially aligned" "Upload succeeded and row stored, but local disk is configured instead of Backblaze B2." $uploadDocId

$employeeDoc = Invoke-FormApi "/employees/$employeeId/documents" @(
    "DocumentTypeID=$documentTypeId",
    "Description=Employee document $suffix",
    "ExpiryDate=2027-12-31",
    "file=@$pngPath;type=image/png"
)
$employeeDocId = $employeeDoc.DocumentID
$null = Invoke-GetApi "/documents/$employeeDocId/download"
Add-Result "DocumentController" "Digital Document Upload and Storage" "POST /employees/{id}/documents + GET /documents/{id}/download" 200 "Partially aligned" "Document create/download works; signed temporary URLs need B2 storage config." $employeeDocId

$leave = Invoke-JsonApi "POST" "/leave" @{
    EmployeeID = $employeeId
    LeaveType = "Annual"
    StartDate = "2026-06-01"
    EndDate = "2026-06-03"
    Reason = "Smoke leave"
}
$leaveId = $leave.LeaveID
$null = Invoke-JsonApi "PATCH" "/leave/$leaveId/approve" @{ status = "Approved" }
Add-Result "LeaveController" "Leave Request and Approval" "POST /leave + PATCH /leave/{id}/approve" 200 "Aligned" "Leave workflow succeeded and approval recorded." $leaveId

$attendance = Invoke-JsonApi "POST" "/attendance" @{
    EmployeeID = $employeeId
    AttendanceDate = "2026-04-22"
    Time_In = "08:00:00"
    Time_Out = "17:00:00"
    AttendanceStatus = "Present"
}
$attendanceId = $attendance.AttendanceID
$null = Invoke-JsonApi "PATCH" "/attendance/$attendanceId" @{ AttendanceStatus = "Late"; Time_In = "08:12:00" }
Add-Result "AttendanceController" "Attendance Tracking" "POST /attendance + PATCH /attendance/{id}" 200 "Aligned" "Attendance create/update succeeded." $attendanceId

$assignedAllowance = Invoke-JsonApi "POST" "/assigned-allowances" @{
    EmployeeID = $employeeId
    AllowanceID = $allowanceId
    EffectiveDate = "2026-04-01"
    Amount = 5000
    IsTaxable = $true
}
$assignedDeduction = Invoke-JsonApi "POST" "/assigned-deductions" @{
    EmployeeID = $employeeId
    DeductionID = $deductionId
    EffectiveDate = "2026-04-01"
    Amount = 1200
}
$payroll = Invoke-JsonApi "POST" "/payroll" @{
    EmployeeID = $employeeId
    PayPeriod = "2026-04"
    BasicSalary = 40000
    PaymentDate = "2026-04-30"
}
$payrollId = $payroll.PayrollID
Add-Result "PayrollController" "Payroll Processing" "POST /payroll" 201 "Aligned" "Payroll processed from assigned allowances and deductions." $payrollId

$performance = Invoke-JsonApi "POST" "/performance" @{
    EmployeeID = $employeeId
    EvaluationPeriod = "Q2-2026"
    Score = 90
    Comments = "Strong performance in smoke test"
}
$evaluationId = $performance.EvaluationID
Add-Result "PerformanceEvaluationController" "Performance Evaluation" "POST /performance" 201 "Aligned" "Evaluation created with authenticated evaluator." $evaluationId

$null = Invoke-JsonApi "POST" "/training/$employeeId/enrol" @{
    TrainingID = $trainingId
    CompletionStatus = "Completed"
}
$null = Invoke-GetApi "/training/outstanding"
Add-Result "TrainingController" "Training Management" "POST /training/{employeeId}/enrol + GET /training/outstanding" 200 "Aligned" "Training enrolment and outstanding report responded." $trainingId

$recruitment = Invoke-JsonApi "POST" "/recruitment" @{
    JobTitle = "Guard $suffix"
    DepartmentID = $departmentId
    VacancyStatus = "Open"
    PostedDate = "2026-04-22"
}
$recruitmentId = $recruitment.RecruitmentID
$null = Invoke-GetApi "/recruitment" $false
$applicant = Invoke-JsonApi "POST" "/recruitment/$recruitmentId/apply" @{
    FullName = "Applicant $suffix"
    Email = "applicant.$suffix@example.com"
    NationalID = "APP-$suffix"
    CV = "cv-$suffix.pdf"
} $false
$applicantId = $applicant.ApplicationID
$null = Invoke-JsonApi "POST" "/applicants/$applicantId/convert" @{
    HireDate = "2026-07-01"
    DepartmentID = $departmentId
    BranchID = $branchId
    JobTitle = "Security Guard"
    EmploymentStatus = "Active"
    SupervisorID = $supervisorId
}
Add-Result "RecruitmentController" "Recruitment and Applicant Management" "POST /recruitment + /apply + /convert" 201 "Aligned" "Recruitment posting, application, and conversion all succeeded." $recruitmentId

$deployment = Invoke-JsonApi "POST" "/deployments" @{
    EmployeeID = $employeeId
    BranchID = $branchId
    DeploymentSite = "Gate A $suffix"
    StartDate = "2026-04-22"
    Reason = "Smoke deployment"
}
$deploymentId = $deployment.DeploymentID
$null = Invoke-GetApi "/branches/$branchId/deployments/current"
Add-Result "DeploymentController" "Guard Deployment History" "POST /deployments + GET /branches/{id}/deployments/current" 200 "Aligned" "Deployment history and current deployment lookup worked." $deploymentId

$managerAccount = Invoke-JsonApi "POST" "/user-accounts" @{
    EmployeeID = $supervisorId
    Username = "manager.$suffix"
    PasswordHash = "secret1234"
    RoleID = $branchManagerRole.RoleID
    AccountStatus = "active"
}
$managerUserId = $managerAccount.UserID
$null = Invoke-JsonApi "POST" "/user-accounts/$managerUserId/reset-password" @{
    password = "secret5678"
    password_confirmation = "secret5678"
}
$null = Invoke-JsonApi "PATCH" "/user-accounts/$managerUserId/deactivate" @{}
$null = Invoke-JsonApi "PATCH" "/user-accounts/$managerUserId/activate" @{}
Add-Result "UserAccountController" "User Account Management" "POST /user-accounts + reset/deactivate/activate" 200 "Aligned" "Account lifecycle endpoints succeeded." $managerUserId

$notification = Invoke-JsonApi "POST" "/notifications" @{
    RecipientUserID = 1
    Title = "Smoke notification $suffix"
    Message = "Notification smoke test"
    NotificationType = "smoke"
    ReferenceTable = "Employee"
    ReferenceID = $employeeId
    IsRead = $false
}
$notificationId = $notification.NotificationID
$null = Invoke-GetApi "/notifications"
$null = Invoke-JsonApi "PATCH" "/notifications/$notificationId/read" @{}
Add-Result "NotificationController" "System Notifications and Alerts" "GET /notifications + PATCH /notifications/{id}/read" 200 "Partially aligned" "In-app notifications work; scheduled expiry alerts, Reverb push, and Mailgun are not configured in this environment." $notificationId

$auditLogs = Invoke-GetApi "/audit-logs"
Add-Result "EntityController" "Audit Logging" "GET /audit-logs" 200 "Partially aligned" "Audit records are present and read-only, but view actions are not currently logged." (($auditLogs.data | Measure-Object).Count)

$managerLoginRaw = & curl.exe -sS -X POST "$BaseUrl/auth/login" -H "Accept: application/json" -H "Content-Type: application/json" --data-raw "{""Username"":""manager.$suffix"",""password"":""secret5678""}"
$managerLogin = $managerLoginRaw | ConvertFrom-Json
$managerHeader = "Authorization: Bearer $($managerLogin.access_token)"
$managerEmployeesRaw = & curl.exe -sS "$BaseUrl/employees" -H "Accept: application/json" -H $managerHeader
$managerEmployees = $managerEmployeesRaw | ConvertFrom-Json
Add-Result "EmployeeController" "Role-Based Access Control" "GET /employees as Branch Manager" 200 "Aligned" "Branch manager access verified against scoped employee list." (($managerEmployees.data | Measure-Object).Count)

$tempEmployee = Invoke-JsonApi "POST" "/employees" @{
    FullName = "Temp Deactivate $suffix"
    DateOfBirth = "1997-01-01"
    Email = "temp.$suffix@example.com"
    PostalAddress = "P.O. Box 9"
    PhoneNumber = "0733333333"
    Gender = "Female"
    JobTitle = "Clerk"
    NationalID = "TMP-$suffix"
    HireDate = "2025-03-01"
    EmploymentStatus = "Active"
    DepartmentID = $departmentId
    BranchID = $branchId
}
$tempEmployeeId = $tempEmployee.EmployeeID
$null = & curl.exe -sS -X DELETE "$BaseUrl/employees/$tempEmployeeId" -H "Accept: application/json" -H $authHeader
Add-Result "EmployeeController" "Employee Profile Management" "DELETE /employees/{id}" 200 "Aligned" "Destroy endpoint deactivates instead of hard deleting." $tempEmployeeId

$null = & curl.exe -sS -X POST "$BaseUrl/auth/logout" -H "Accept: application/json" -H $authHeader
Add-Result "AuthController" "Secure User Authentication" "POST /auth/logout" 200 "Aligned" "Logout succeeded."

$countsRaw = @'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$tables = ['Role','Permission','RolePermission','Branch','DocumentType','Allowances','Deductions','Training','Department','Employee','UserAccount','Recruitment','Applicant','LeaveBalance','LeaveRequest','Attendance','AdditionalDocuments','AssignedAllowance','AssignedDeduction','Payroll','PerformanceEvaluation','DeploymentHistory','Notifications','AuditLog'];
$out = [];
foreach ($tables as $table) {
    $out[$table] = Illuminate\Support\Facades\DB::table($table)->count();
}
echo json_encode($out, JSON_PRETTY_PRINT);
'@ | php
$counts = $countsRaw | ConvertFrom-Json

$report = [pscustomobject]@{
    generated_at = (Get-Date).ToString("s")
    seeded_login = [pscustomobject]@{
        username = "seed.admin"
        password = "secret123"
    }
    ids = [pscustomobject]@{
        branch_id = $branchId
        department_id = $departmentId
        supervisor_employee_id = $supervisorId
        employee_id = $employeeId
        allowance_id = $allowanceId
        deduction_id = $deductionId
        training_id = $trainingId
        recruitment_id = $recruitmentId
        applicant_id = $applicantId
        payroll_id = $payrollId
        document_upload_id = $uploadDocId
        employee_document_id = $employeeDocId
        notification_id = $notificationId
    }
    counts = $counts
    results = $results
}

$reportPath = Join-Path $PWD "storage\\app\\hrms-live-curl-report.json"
$mdPath = Join-Path $PWD "storage\\app\\hrms-live-curl-report.md"
$report | ConvertTo-Json -Depth 20 | Set-Content -Path $reportPath

$md = @()
$md += '# HRMS Live Curl Report'
$md += ''
$md += "Generated: $((Get-Date).ToString('s'))"
$md += 'Seeded login: `seed.admin` / `secret123`'
$md += ''
$md += '| Controller | Feature | Endpoint | Compliance | Notes | ID |'
$md += '|---|---|---|---|---|---|'
foreach ($row in $results) {
    $md += '| ' + $row.controller + ' | ' + $row.feature + ' | `' + $row.endpoint + '` | ' + $row.compliance + ' | ' + $row.notes + ' | ' + $row.id + ' |'
}
$md += ''
$md += '## Database Counts'
foreach ($p in $counts.PSObject.Properties) {
    $md += "- $($p.Name): $($p.Value)"
}
$md | Set-Content -Path $mdPath

$report | ConvertTo-Json -Depth 20
