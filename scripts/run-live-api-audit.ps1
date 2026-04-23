$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$baseUrl = 'http://127.0.0.1:8000'
$outputDir = Join-Path $root 'storage\app\api-audit'
$fixturesDir = Join-Path $outputDir 'fixtures'
$responsesDir = Join-Path $outputDir 'responses'
$downloadsDir = Join-Path $outputDir 'downloads'
$logPath = Join-Path $outputDir 'curl-log.md'

New-Item -ItemType Directory -Force -Path $outputDir, $fixturesDir, $responsesDir, $downloadsDir | Out-Null

function Write-PdfFixture {
    param(
        [string]$Path,
        [string]$Title
    )

    $content = @(
        '%PDF-1.4'
        '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj'
        '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj'
        '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R >> endobj'
        '4 0 obj << /Length 44 >> stream'
        "BT /F1 12 Tf 24 100 Td ($Title) Tj ET"
        'endstream endobj'
        'xref'
        '0 5'
        '0000000000 65535 f '
        'trailer << /Size 5 /Root 1 0 R >>'
        'startxref'
        '0'
        '%%EOF'
    ) -join "`n"

    Set-Content -Path $Path -Value $content -Encoding Ascii
}

$fixtures = @{
    Letter = Join-Path $fixturesDir 'letter-of-application.pdf'
    Certificate = Join-Path $fixturesDir 'highest-level-certificate.pdf'
    Cv = Join-Path $fixturesDir 'cv.pdf'
    GoodConduct = Join-Path $fixturesDir 'good-conduct.pdf'
    NationalId = Join-Path $fixturesDir 'national-id.pdf'
    Contract = Join-Path $fixturesDir 'contract.pdf'
}

Write-PdfFixture -Path $fixtures.Letter -Title 'API Audit Letter'
Write-PdfFixture -Path $fixtures.Certificate -Title 'API Audit Certificate'
Write-PdfFixture -Path $fixtures.Cv -Title 'API Audit CV'
Write-PdfFixture -Path $fixtures.GoodConduct -Title 'API Audit Good Conduct'
Write-PdfFixture -Path $fixtures.NationalId -Title 'API Audit National ID'
Write-PdfFixture -Path $fixtures.Contract -Title 'API Audit Contract'

Set-Content -Path $logPath -Value "# Live Curl Audit`n" -Encoding UTF8

function Quote-Arg {
    param([string]$Value)

    if ($Value -match '[\s"`$]') {
        return '"' + ($Value -replace '"', '\"') + '"'
    }

    return $Value
}

function Invoke-Curl {
    param(
        [string]$Name,
        [string]$Method,
        [string]$Path,
        [hashtable]$Headers = @{},
        [object]$JsonBody = $null,
        [string[]]$FormFields = @(),
        [string]$DownloadFile = '',
        [switch]$ExpectJson
    )

    $slug = ($Name -replace '[^a-zA-Z0-9\-]+', '-').ToLowerInvariant()
    $headerFile = Join-Path $responsesDir "$slug.headers.txt"
    $bodyFile = if ($DownloadFile) { $DownloadFile } else { Join-Path $responsesDir "$slug.body.txt" }
    $url = "$baseUrl$Path"

    $args = @('-sS', '-X', $Method, '-D', $headerFile, '-o', $bodyFile)

    foreach ($key in $Headers.Keys) {
        $args += @('-H', ('{0}: {1}' -f $key, $Headers[$key]))
    }

    if ($null -ne $JsonBody) {
        $args += @('-H', 'Content-Type: application/json', '--data-raw', ($JsonBody | ConvertTo-Json -Depth 10 -Compress))
    }

    foreach ($field in $FormFields) {
        $args += @('-F', $field)
    }

    $args += $url

    & curl.exe @args

    $statusLine = Get-Content $headerFile | Select-Object -First 1
    $statusCode = [int](($statusLine -split ' ')[1])
    $body = if (Test-Path $bodyFile) { Get-Content $bodyFile -Raw } else { '' }

    Add-Content -Path $logPath -Value "## $Name`n"
    Add-Content -Path $logPath -Value "```bash"
    Add-Content -Path $logPath -Value ('curl.exe ' + (($args | ForEach-Object { Quote-Arg $_ }) -join ' '))
    Add-Content -Path $logPath -Value "```"
    Add-Content -Path $logPath -Value "`nStatus: $statusCode`n"

    if (-not $DownloadFile) {
        Add-Content -Path $logPath -Value ("Response file: responses/{0}.body.txt`n" -f $slug)
    } else {
        Add-Content -Path $logPath -Value ("Download file: downloads/{0}`n" -f [IO.Path]::GetFileName($DownloadFile))
    }

    if ($ExpectJson -and $body) {
        $pretty = $body | ConvertFrom-Json | ConvertTo-Json -Depth 12
        Set-Content -Path $bodyFile -Value $pretty -Encoding UTF8
    }

    return [pscustomobject]@{
        StatusCode = $statusCode
        BodyFile = $bodyFile
        HeaderFile = $headerFile
        BodyRaw = if (Test-Path $bodyFile) { Get-Content $bodyFile -Raw } else { '' }
    }
}

function Get-Json {
    param([string]$Path)
    return (Get-Content $Path -Raw | ConvertFrom-Json)
}

php artisan db:seed --class=HrmsPermissionSeeder | Out-Null
php artisan db:seed --class=RecruitmentMockDataSeeder | Out-Null

$login = Invoke-Curl -Name 'auth-login' -Method 'POST' -Path '/api/auth/login' -JsonBody @{
    Username = 'seed.admin'
    password = 'secret123'
} -ExpectJson

$loginJson = Get-Json $login.BodyFile
$token = $loginJson.access_token
$authHeaders = @{
    Authorization = "Bearer $token"
    Accept = 'application/json'
}

$me = Invoke-Curl -Name 'auth-me' -Method 'GET' -Path '/api/user' -Headers $authHeaders -ExpectJson
$publicRecruitment = Invoke-Curl -Name 'public-recruitment-index' -Method 'GET' -Path '/api/recruitment' -ExpectJson

$branch = Invoke-Curl -Name 'branches-store' -Method 'POST' -Path '/api/branches' -Headers $authHeaders -JsonBody @{
    BranchName = 'API Audit Branch'
    BranchLocation = 'Nairobi West'
    BranchPhone = '0701111111'
    BranchEmail = 'api.audit.branch@example.com'
} -ExpectJson
$branchId = (Get-Json $branch.BodyFile).BranchID

$role = Invoke-Curl -Name 'roles-store' -Method 'POST' -Path '/api/roles' -Headers $authHeaders -JsonBody @{
    RoleName = 'API Audit Role'
    RoleDescription = 'Role created by live curl audit'
} -ExpectJson
$roleId = (Get-Json $role.BodyFile).RoleID

$permission = Invoke-Curl -Name 'permissions-store' -Method 'POST' -Path '/api/permissions' -Headers $authHeaders -JsonBody @{
    PermissionName = 'api-audit-permission'
    Description = 'Permission created by live curl audit'
} -ExpectJson
$permissionId = (Get-Json $permission.BodyFile).PermissionID

$rolePermission = Invoke-Curl -Name 'role-permissions-store' -Method 'POST' -Path '/api/role-permissions' -Headers $authHeaders -JsonBody @{
    RoleID = $roleId
    PermissionID = $permissionId
} -ExpectJson
$rolePermissionId = (Get-Json $rolePermission.BodyFile).RolePermissionID

$documentType = Invoke-Curl -Name 'document-types-store' -Method 'POST' -Path '/api/document-types' -Headers $authHeaders -JsonBody @{
    TypeName = 'API Audit Document'
    TypeDescription = 'Document type for live curl audit'
} -ExpectJson
$documentTypeId = (Get-Json $documentType.BodyFile).DocumentTypeID

$allowance = Invoke-Curl -Name 'allowances-store' -Method 'POST' -Path '/api/allowances' -Headers $authHeaders -JsonBody @{
    AllowanceName = 'API Audit Housing'
} -ExpectJson
$allowanceId = (Get-Json $allowance.BodyFile).AllowanceID

$deduction = Invoke-Curl -Name 'deductions-store' -Method 'POST' -Path '/api/deductions' -Headers $authHeaders -JsonBody @{
    DeductionName = 'API Audit PAYE'
    Rate = 0.3
} -ExpectJson
$deductionId = (Get-Json $deduction.BodyFile).DeductionID

$training = Invoke-Curl -Name 'trainings-store' -Method 'POST' -Path '/api/trainings' -Headers $authHeaders -JsonBody @{
    TrainingName = 'API Audit Orientation'
    TrainingType = 'Mandatory'
    StartDate = '2026-04-20'
    EndDate = '2026-04-21'
} -ExpectJson
$trainingId = (Get-Json $training.BodyFile).TrainingID

$department = Invoke-Curl -Name 'departments-store' -Method 'POST' -Path '/api/departments' -Headers $authHeaders -JsonBody @{
    DepartmentName = 'API Audit Operations'
    BranchID = $branchId
    DepartmentDescription = 'Department created by live curl audit'
    CreatedDate = '2026-04-23'
} -ExpectJson
$departmentId = (Get-Json $department.BodyFile).DepartmentID

$recruitment = Invoke-Curl -Name 'recruitment-store' -Method 'POST' -Path '/api/recruitment' -Headers $authHeaders -JsonBody @{
    JobTitle = 'API Audit Guard'
    DepartmentID = $departmentId
    VacancyStatus = 'Open'
    PostedDate = '2026-04-23'
} -ExpectJson
$recruitmentId = (Get-Json $recruitment.BodyFile).RecruitmentID

$employee = Invoke-Curl -Name 'employees-store' -Method 'POST' -Path '/api/employees' -Headers $authHeaders -JsonBody @{
    FirstName = 'Audit'
    LastName = 'Employee'
    DateOfBirth = '1995-03-10'
    Email = 'api.audit.employee@example.com'
    PostalAddress = 'P.O. Box 500'
    PhoneNumber = '0722222222'
    Gender = 'Male'
    JobTitle = 'Security Officer'
    NationalID = 'API-EMP-001'
    HireDate = '2026-04-23'
    EmploymentStatus = 'Active'
    DepartmentID = $departmentId
    BranchID = $branchId
} -ExpectJson
$employeeId = (Get-Json $employee.BodyFile).EmployeeID

$employeeUpdate = Invoke-Curl -Name 'employees-update' -Method 'PATCH' -Path "/api/employees/$employeeId" -Headers $authHeaders -JsonBody @{
    JobTitle = 'Senior Security Officer'
    EmploymentStatus = 'Active'
    Email = 'api.audit.employee@example.com'
    NationalID = 'API-EMP-001'
    HireDate = '2026-04-23'
    DepartmentID = $departmentId
    BranchID = $branchId
} -ExpectJson

$leaveBalance = Invoke-Curl -Name 'leave-balances-store' -Method 'POST' -Path '/api/leave-balances' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    LeaveType = 'Annual'
    TotalDays = 21
    UsedDays = 0
} -ExpectJson
$leaveBalanceId = (Get-Json $leaveBalance.BodyFile).LeaveBalanceID

$assignedAllowance = Invoke-Curl -Name 'assigned-allowances-store' -Method 'POST' -Path '/api/assigned-allowances' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    AllowanceID = $allowanceId
    EffectiveDate = '2026-04-23'
    Amount = 5000
    IsTaxable = $true
} -ExpectJson
$assignedAllowanceId = (Get-Json $assignedAllowance.BodyFile).AssignedAllowanceID

$assignedDeduction = Invoke-Curl -Name 'assigned-deductions-store' -Method 'POST' -Path '/api/assigned-deductions' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    DeductionID = $deductionId
    EffectiveDate = '2026-04-23'
    Amount = 1500
} -ExpectJson
$assignedDeductionId = (Get-Json $assignedDeduction.BodyFile).AssignedDeductionID

$attendance = Invoke-Curl -Name 'attendance-store' -Method 'POST' -Path '/api/attendance' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    AttendanceDate = '2026-04-23'
    Time_In = '08:00:00'
    Time_Out = '17:00:00'
    AttendanceStatus = 'Present'
} -ExpectJson
$attendanceId = (Get-Json $attendance.BodyFile).AttendanceID

$attendanceUpdate = Invoke-Curl -Name 'attendance-update' -Method 'PATCH' -Path "/api/attendance/$attendanceId" -Headers $authHeaders -JsonBody @{
    AttendanceStatus = 'Late'
    Time_In = '08:10:00'
    Time_Out = '17:05:00'
} -ExpectJson

$leave = Invoke-Curl -Name 'leave-store' -Method 'POST' -Path '/api/leave' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    LeaveType = 'Annual'
    StartDate = '2026-05-05'
    EndDate = '2026-05-07'
    Reason = 'Family event'
} -ExpectJson
$leaveId = (Get-Json $leave.BodyFile).LeaveID

$leaveApprove = Invoke-Curl -Name 'leave-approve' -Method 'PATCH' -Path "/api/leave/$leaveId/approve" -Headers $authHeaders -JsonBody @{
    status = 'Approved'
} -ExpectJson

$performance = Invoke-Curl -Name 'performance-store' -Method 'POST' -Path '/api/performance' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    EvaluationPeriod = 'Q2-2026'
    Score = 88
    Comments = 'Reliable performance during audit cycle'
} -ExpectJson
$evaluationId = (Get-Json $performance.BodyFile).EvaluationID

$trainingEnrol = Invoke-Curl -Name 'training-enrol' -Method 'POST' -Path "/api/training/$employeeId/enrol" -Headers $authHeaders -JsonBody @{
    TrainingID = $trainingId
    CompletionStatus = 'Completed'
} -ExpectJson

$deployment = Invoke-Curl -Name 'deployments-store' -Method 'POST' -Path '/api/deployments' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    BranchID = $branchId
    DeploymentSite = 'Audit Site A'
    StartDate = '2026-04-23'
    Reason = 'Initial deployment'
} -ExpectJson
$deploymentId = (Get-Json $deployment.BodyFile).DeploymentID

$userAccount = Invoke-Curl -Name 'user-accounts-store' -Method 'POST' -Path '/api/user-accounts' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    Username = 'api.audit.employee'
    password = 'secret1234'
    password_confirmation = 'secret1234'
    RoleID = $roleId
} -ExpectJson
$userId = (Get-Json $userAccount.BodyFile).UserID

$notification = Invoke-Curl -Name 'notifications-store' -Method 'POST' -Path '/api/notifications' -Headers $authHeaders -JsonBody @{
    RecipientUserID = $userId
    Title = 'API Audit Notification'
    Message = 'This notification was created by the live curl audit.'
    NotificationType = 'api-audit'
    ReferenceTable = 'Employee'
    ReferenceID = $employeeId
    IsRead = $false
} -ExpectJson
$notificationId = (Get-Json $notification.BodyFile).NotificationID

$documentUpload = Invoke-Curl -Name 'upload-endpoint-store' -Method 'POST' -Path '/api/upload' -Headers @{ Authorization = "Bearer $token" } -FormFields @(
    "EmployeeID=$employeeId",
    "DocumentTypeID=$documentTypeId",
    "Description=API upload endpoint document",
    "file=@$($fixtures.NationalId);type=application/pdf"
) -ExpectJson
$documentIdFromUpload = (Get-Json $documentUpload.BodyFile).document.DocumentID

$employeeDocument = Invoke-Curl -Name 'employee-document-store' -Method 'POST' -Path "/api/employees/$employeeId/documents" -Headers @{ Authorization = "Bearer $token" } -FormFields @(
    "DocumentTypeID=$documentTypeId",
    "Description=API employee document",
    "ExpiryDate=2027-04-23",
    "file=@$($fixtures.Contract);type=application/pdf"
) -ExpectJson
$employeeDocumentId = (Get-Json $employeeDocument.BodyFile).DocumentID

$publicApply = Invoke-Curl -Name 'recruitment-apply-public' -Method 'POST' -Path "/api/recruitment/$recruitmentId/apply" -Headers @{ Accept = 'application/json' } -FormFields @(
    'FirstName=Public',
    'LastName=Applicant',
    'Email=public.applicant@example.com',
    'Address=P.O. Box 600',
    'PhoneNumber=0733333333',
    'Gender=Female',
    'NationalID=API-APP-001',
    "LetterOfApplication=@$($fixtures.Letter);type=application/pdf",
    "HighestLevelCertificate=@$($fixtures.Certificate);type=application/pdf",
    "CV=@$($fixtures.Cv);type=application/pdf",
    "GoodConduct=@$($fixtures.GoodConduct);type=application/pdf"
) -ExpectJson
$applicantId = (Get-Json $publicApply.BodyFile).ApplicationID

$convertApplicant = Invoke-Curl -Name 'applicant-convert' -Method 'POST' -Path "/api/applicants/$applicantId/convert" -Headers $authHeaders -JsonBody @{
    HireDate = '2026-05-01'
    DepartmentID = $departmentId
    BranchID = $branchId
    JobTitle = 'Guard Recruit'
    EmploymentStatus = 'Active'
    SupervisorID = $employeeId
} -ExpectJson
$convertedEmployeeId = (Get-Json $convertApplicant.BodyFile).EmployeeID

$payroll = Invoke-Curl -Name 'payroll-store' -Method 'POST' -Path '/api/payroll' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    PayPeriod = '2026-05'
    BasicSalary = 50000
    PaymentDate = '2026-05-31'
} -ExpectJson
$payrollId = (Get-Json $payroll.BodyFile).PayrollID

$payrollAllowance = Invoke-Curl -Name 'payroll-allowances-store' -Method 'POST' -Path '/api/payroll-allowances' -Headers $authHeaders -JsonBody @{
    PayrollID = $payrollId
    AssignedAllowanceID = $assignedAllowanceId
} -ExpectJson

$payrollDeduction = Invoke-Curl -Name 'payroll-deductions-store' -Method 'POST' -Path '/api/payroll-deductions' -Headers $authHeaders -JsonBody @{
    PayrollID = $payrollId
    AssignedDeductionID = $assignedDeductionId
} -ExpectJson

$genericAdditionalDocument = Invoke-Curl -Name 'additional-documents-store' -Method 'POST' -Path '/api/additional-documents' -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    DocumentTypeID = $documentTypeId
    Document = 'manual/audit-reference.pdf'
    Description = 'Manual additional document reference'
    ExpiryDate = '2027-04-23'
    UploadedBy = $employeeId
} -ExpectJson
$additionalDocumentId = (Get-Json $genericAdditionalDocument.BodyFile).DocumentID

$employeeTraining = Invoke-Curl -Name 'employee-trainings-store' -Method 'POST' -Path '/api/employee-trainings' -Headers $authHeaders -JsonBody @{
    EmployeeID = $convertedEmployeeId
    TrainingID = $trainingId
    CompletionStatus = 'Enrolled'
} -ExpectJson
$employeeTrainingId = (Get-Json $employeeTraining.BodyFile).EmployeeTrainingID

$employeesIndexPath = '/api/employees?search=audit&DepartmentID={0}&BranchID={1}&EmploymentStatus=Active' -f $departmentId, $branchId
$leaveIndexPath = '/api/leave?EmployeeID={0}&LeaveStatus=Approved' -f $employeeId
$attendanceIndexPath = '/api/attendance?EmployeeID={0}&from=2026-04-01&to=2026-04-30' -f $employeeId
$payrollIndexPath = '/api/payroll?EmployeeID={0}&PayPeriod=2026-05' -f $employeeId
$deploymentsIndexPath = '/api/deployments?EmployeeID={0}&BranchID={1}' -f $employeeId, $branchId

Invoke-Curl -Name 'employees-index' -Method 'GET' -Path $employeesIndexPath -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'employees-show' -Method 'GET' -Path "/api/employees/$employeeId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'employees-export-csv' -Method 'GET' -Path '/api/employees?export=csv' -Headers $authHeaders -DownloadFile (Join-Path $downloadsDir 'employees.csv') | Out-Null
Invoke-Curl -Name 'employees-export-pdf' -Method 'GET' -Path '/api/employees?export=pdf' -Headers $authHeaders -DownloadFile (Join-Path $downloadsDir 'employees.pdf') | Out-Null
Invoke-Curl -Name 'documents-download' -Method 'GET' -Path "/api/documents/$employeeDocumentId/download" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'leave-index' -Method 'GET' -Path $leaveIndexPath -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'attendance-index' -Method 'GET' -Path $attendanceIndexPath -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'payroll-index' -Method 'GET' -Path $payrollIndexPath -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'payroll-show' -Method 'GET' -Path "/api/payroll/$payrollId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'performance-index' -Method 'GET' -Path '/api/performance' -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'training-index' -Method 'GET' -Path '/api/training' -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'training-outstanding' -Method 'GET' -Path '/api/training/outstanding' -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'deployments-index' -Method 'GET' -Path $deploymentsIndexPath -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'deployments-current' -Method 'GET' -Path "/api/branches/$branchId/deployments/current" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'user-accounts-index' -Method 'GET' -Path '/api/user-accounts' -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'user-accounts-show' -Method 'GET' -Path "/api/user-accounts/$userId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'user-accounts-update' -Method 'PATCH' -Path "/api/user-accounts/$userId" -Headers $authHeaders -JsonBody @{
    EmployeeID = $employeeId
    Username = 'api.audit.employee'
    RoleID = $roleId
    AccountStatus = 'active'
} -ExpectJson | Out-Null
Invoke-Curl -Name 'user-accounts-reset-password' -Method 'POST' -Path "/api/user-accounts/$userId/reset-password" -Headers $authHeaders -JsonBody @{
    password = 'secret5678'
    password_confirmation = 'secret5678'
} -ExpectJson | Out-Null
Invoke-Curl -Name 'user-accounts-deactivate' -Method 'PATCH' -Path "/api/user-accounts/$userId/deactivate" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'user-accounts-activate' -Method 'PATCH' -Path "/api/user-accounts/$userId/activate" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'notifications-index' -Method 'GET' -Path '/api/notifications' -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'notifications-mark-read' -Method 'PATCH' -Path "/api/notifications/$notificationId/read" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'audit-logs-index' -Method 'GET' -Path '/api/audit-logs' -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'audit-logs-store-method-not-allowed' -Method 'POST' -Path '/api/audit-logs' -Headers $authHeaders -JsonBody @{
    Action = 'manual'
    AffectedTable = 'Employee'
} -ExpectJson | Out-Null

Invoke-Curl -Name 'branches-show' -Method 'GET' -Path "/api/branches/$branchId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'branches-update' -Method 'PATCH' -Path "/api/branches/$branchId" -Headers $authHeaders -JsonBody @{
    BranchName = 'API Audit Branch Updated'
    BranchLocation = 'Nairobi West'
    BranchPhone = '0701111111'
    BranchEmail = 'api.audit.branch@example.com'
} -ExpectJson | Out-Null

Invoke-Curl -Name 'roles-show' -Method 'GET' -Path "/api/roles/$roleId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'permissions-show' -Method 'GET' -Path "/api/permissions/$permissionId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'document-types-show' -Method 'GET' -Path "/api/document-types/$documentTypeId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'allowances-show' -Method 'GET' -Path "/api/allowances/$allowanceId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'allowances-update' -Method 'PATCH' -Path "/api/allowances/$allowanceId" -Headers $authHeaders -JsonBody @{ AllowanceName = 'API Audit Housing Updated' } -ExpectJson | Out-Null
Invoke-Curl -Name 'deductions-show' -Method 'GET' -Path "/api/deductions/$deductionId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'deductions-update' -Method 'PATCH' -Path "/api/deductions/$deductionId" -Headers $authHeaders -JsonBody @{ DeductionName = 'API Audit PAYE Updated'; Rate = 0.25 } -ExpectJson | Out-Null
Invoke-Curl -Name 'trainings-show' -Method 'GET' -Path "/api/trainings/$trainingId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'departments-show' -Method 'GET' -Path "/api/departments/$departmentId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'applicants-show' -Method 'GET' -Path "/api/applicants/$applicantId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'leave-balances-show' -Method 'GET' -Path "/api/leave-balances/$leaveBalanceId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'additional-documents-show' -Method 'GET' -Path "/api/additional-documents/$additionalDocumentId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'deployment-history-show' -Method 'GET' -Path "/api/deployment-history/$deploymentId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'employee-trainings-show' -Method 'GET' -Path "/api/employee-trainings/$employeeTrainingId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'assigned-allowances-show' -Method 'GET' -Path "/api/assigned-allowances/$assignedAllowanceId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'assigned-deductions-show' -Method 'GET' -Path "/api/assigned-deductions/$assignedDeductionId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'payroll-allowances-show' -Method 'GET' -Path "/api/payroll-allowances/$((Get-Json $payrollAllowance.BodyFile).PayrollAllowanceID)" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'payroll-deductions-show' -Method 'GET' -Path "/api/payroll-deductions/$((Get-Json $payrollDeduction.BodyFile).PayrollDeductionID)" -Headers $authHeaders -ExpectJson | Out-Null

Invoke-Curl -Name 'user-accounts-destroy' -Method 'DELETE' -Path "/api/user-accounts/$userId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'employees-destroy' -Method 'DELETE' -Path "/api/employees/$convertedEmployeeId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'allowances-destroy' -Method 'DELETE' -Path "/api/allowances/$allowanceId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'deductions-destroy' -Method 'DELETE' -Path "/api/deductions/$deductionId" -Headers $authHeaders -ExpectJson | Out-Null
Invoke-Curl -Name 'auth-logout' -Method 'POST' -Path '/api/auth/logout' -Headers $authHeaders -ExpectJson | Out-Null

$summary = [pscustomobject]@{
    GeneratedAt = (Get-Date).ToString('s')
    BaseUrl = $baseUrl
    AdminUsername = 'seed.admin'
    OutputDirectory = $outputDir
    CreatedIds = [pscustomobject]@{
        BranchID = $branchId
        RoleID = $roleId
        PermissionID = $permissionId
        RolePermissionID = $rolePermissionId
        DocumentTypeID = $documentTypeId
        EmployeeID = $employeeId
        RecruitmentID = $recruitmentId
        ApplicantID = $applicantId
        ConvertedEmployeeID = $convertedEmployeeId
        LeaveID = $leaveId
        AttendanceID = $attendanceId
        PayrollID = $payrollId
        EvaluationID = $evaluationId
        DeploymentID = $deploymentId
        UserID = $userId
        NotificationID = $notificationId
        EmployeeDocumentID = $employeeDocumentId
    }
}

$summary | ConvertTo-Json -Depth 8 | Set-Content -Path (Join-Path $outputDir 'summary.json') -Encoding UTF8
Write-Host "Live curl audit complete. Artifacts saved to $outputDir"
