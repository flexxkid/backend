# HRMS API Reference

Base URL: `http://127.0.0.1:8000/api`

Live curl evidence for this reference was captured in:
- [curl-log.md](/C:/Users/PC/Projects/backend/storage/app/api-audit/curl-log.md)
- [summary.json](/C:/Users/PC/Projects/backend/storage/app/api-audit/summary.json)

## Authentication

### `POST /auth/register`
- Auth: Public
- Purpose: Create a user account and immediately return a Sanctum token.
- Request JSON:
  - `EmployeeID` nullable integer
  - `RoleID` nullable integer
  - `Username` required string
  - `password` required string, min 8
  - `password_confirmation` required string
  - `account_status` nullable string
- Response:
  - `201 Created`
  - `access_token`, `token_type`, `user`

### `POST /auth/login`
- Auth: Public
- Purpose: Authenticate and return a bearer token.
- Request JSON:
  - `Username` required string
  - `password` required string
- Response:
  - `200 OK`
  - `access_token`, `token_type`, `user`
- Example:
  - [auth-login.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/auth-login.body.txt)

### `GET /user`
- Auth: Bearer token
- Purpose: Fetch the currently authenticated user with `employee`, `role`, and `notifications`.
- Response:
  - `200 OK`
- Example:
  - [auth-me.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/auth-me.body.txt)

### `POST /auth/logout`
- Auth: Bearer token
- Purpose: Revoke the current access token.
- Response:
  - `200 OK`
  - `message`

## Public Recruitment

### `GET /recruitment`
- Auth: Public
- Purpose: List recruitment postings with department and applicants.
- Query:
  - `per_page` optional integer
- Response:
  - `200 OK`
- Example:
  - [public-recruitment-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/public-recruitment-index.body.txt)

### `POST /recruitment/{recruitmentId}/apply`
- Auth: Public
- Purpose: Submit an applicant with uploaded files.
- Request `multipart/form-data`:
  - `FirstName` or `FullName`
  - `LastName` when using split names
  - `DateOfBirth`, `Email`, `Address`, `PhoneNumber`, `Gender`
  - `NationalID` required
  - `LetterOfApplication` optional file
  - `HighestLevelCertificate` optional file
  - `CV` optional file
  - `GoodConduct` optional file
- Response:
  - `201 Created`
  - applicant payload with stored file paths
- Example:
  - [recruitment-apply-public.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/recruitment-apply-public.body.txt)

## Employees

### `GET /employees`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Purpose: Paginated employee listing with search and exports.
- Query:
  - `search`
  - `DepartmentID`
  - `BranchID`
  - `EmploymentStatus`
  - `per_page`
  - `export=csv|pdf`
- Response:
  - `200 OK`
  - paginated JSON unless exporting
- Examples:
  - [employees-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/employees-index.body.txt)
  - [employees.csv](/C:/Users/PC/Projects/backend/storage/app/api-audit/downloads/employees.csv)
  - [employees.pdf](/C:/Users/PC/Projects/backend/storage/app/api-audit/downloads/employees.pdf)

### `POST /employees`
- Auth: `HR Administrator`, `HR Officer`
- Purpose: Create an employee profile.
- Request JSON:
  - `FirstName` and `LastName` or `FullName`
  - `DateOfBirth`, `Email`, `PostalAddress`, `PhoneNumber`, `Gender`
  - `JobTitle`
  - `NationalID`
  - `HireDate`
  - `EmploymentStatus`
  - `DepartmentID`
  - `SupervisorID` nullable
  - `BranchID`
- Response:
  - `201 Created`
- Example:
  - [employees-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/employees-store.body.txt)

### `GET /employees/{id}`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Purpose: Get one employee with related documents, balances, deployments, and evaluations.
- Response:
  - `200 OK`
- Example:
  - [employees-show.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/employees-show.body.txt)

### `PATCH /employees/{id}`
- Auth: `HR Administrator`, `HR Officer`
- Purpose: Update employee fields.
- Request JSON:
  - Any subset of the create fields
- Response:
  - `200 OK`

### `DELETE /employees/{id}`
- Auth: `HR Administrator`
- Purpose: Soft deactivate the employee by setting `EmploymentStatus=Inactive`.
- Response:
  - `200 OK`
  - `message`

## Documents

### `POST /employees/{employeeId}/documents`
- Auth: `HR Administrator`, `HR Officer`
- Purpose: Upload a document for a specific employee.
- Request `multipart/form-data`:
  - `file` required
  - `DocumentTypeID` required
  - `Description` optional
  - `ExpiryDate` optional
- Response:
  - `201 Created`
- Example:
  - [employee-document-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/employee-document-store.body.txt)

### `GET /documents/{documentId}/download`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Purpose: Get a file access URL.
- Response:
  - `200 OK`
  - `url`
- Example:
  - [documents-download.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/documents-download.body.txt)

### `POST /upload`
- Auth: `HR Administrator`, `HR Officer`
- Purpose: Generic additional document upload endpoint.
- Request `multipart/form-data`:
  - `EmployeeID`
  - `DocumentTypeID`
  - `file`
  - `Description` optional
  - `ExpiryDate` optional
- Response:
  - `201 Created`
  - `message`, `document`, `url`
- Example:
  - [upload-endpoint-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/upload-endpoint-store.body.txt)

## Leave

### `GET /leave`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Query:
  - `EmployeeID`
  - `LeaveStatus`
  - `per_page`
- Response:
  - `200 OK`
- Example:
  - [leave-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/leave-index.body.txt)

### `POST /leave`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Request JSON:
  - `EmployeeID`
  - `LeaveType` one of `Annual|Sick|Maternity|Emergency`
  - `StartDate`
  - `EndDate`
  - `Reason` optional
- Response:
  - `201 Created`
- Example:
  - [leave-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/leave-store.body.txt)

### `PATCH /leave/{id}/approve`
- Auth: `HR Administrator`, `Branch Manager`
- Request JSON:
  - `status` one of `Approved|Rejected`
- Response:
  - `200 OK`
- Example:
  - [leave-approve.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/leave-approve.body.txt)

## Attendance

### `GET /attendance`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Query:
  - `EmployeeID`
  - `from`
  - `to`
  - `per_page`
- Response:
  - `200 OK`
- Example:
  - [attendance-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/attendance-index.body.txt)

### `POST /attendance`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`
- Request JSON:
  - `EmployeeID`
  - `AttendanceDate`
  - `Time_In`
  - `Time_Out`
  - `AttendanceStatus`
- Response:
  - `201 Created`
- Example:
  - [attendance-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/attendance-store.body.txt)

### `PATCH /attendance/{id}`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`
- Request JSON:
  - `AttendanceDate` currently required by live server behavior when updating
  - `Time_In` optional
  - `Time_Out` optional
  - `AttendanceStatus` optional
- Response:
  - `200 OK` on success
  - live audit on port `8000` captured a `422` without `AttendanceDate`
- Evidence:
  - [attendance-update.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/attendance-update.body.txt)

## Payroll

### `GET /payroll`
- Auth: `HR Administrator`
- Query:
  - `EmployeeID`
  - `PayPeriod`
  - `per_page`
- Response:
  - `200 OK`
- Example:
  - [payroll-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/payroll-index.body.txt)

### `POST /payroll`
- Auth: `HR Administrator`
- Request JSON:
  - `EmployeeID`
  - `PayPeriod`
  - `BasicSalary`
  - `PaymentDate`
- Response:
  - `201 Created`
  - payroll with employee, allowances, deductions
- Example:
  - [payroll-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/payroll-store.body.txt)

### `GET /payroll/{id}`
- Auth: `HR Administrator`
- Response:
  - `200 OK`
- Example:
  - [payroll-show.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/payroll-show.body.txt)

## Performance

### `GET /performance`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`, `Employee`
- Purpose: Paginated evaluations with role-aware visibility.
- Response:
  - `200 OK`
- Example:
  - [performance-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/performance-index.body.txt)

### `POST /performance`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`
- Request JSON:
  - `EmployeeID`
  - `EvaluationPeriod`
  - `Score`
  - `Comments` optional
- Response:
  - `201 Created`
- Example:
  - [performance-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/performance-store.body.txt)

## Training

### `GET /training`
- Auth: `HR Administrator`, `HR Officer`, `Branch Manager`, `Auditor`
- Response:
  - `200 OK`
- Example:
  - [training-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/training-index.body.txt)

### `POST /training/{employeeId}/enrol`
- Auth: `HR Administrator`, `HR Officer`
- Request JSON:
  - `TrainingID`
  - `CompletionStatus` one of `Enrolled|Completed|Failed`
- Response:
  - `200 OK`
- Example:
  - [training-enrol.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/training-enrol.body.txt)

### `GET /training/outstanding`
- Auth: `HR Administrator`, `HR Officer`, `Branch Manager`
- Response:
  - `200 OK`
- Example:
  - [training-outstanding.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/training-outstanding.body.txt)

## Recruitment Admin

### `POST /recruitment`
- Auth: `HR Administrator`
- Request JSON:
  - `JobTitle`
  - `DepartmentID`
  - `VacancyStatus`
  - `PostedDate`
- Response:
  - `201 Created`
- Example:
  - [recruitment-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/recruitment-store.body.txt)

### `POST /applicants/{applicantId}/convert`
- Auth: `HR Administrator`
- Request JSON:
  - `HireDate`
  - `DepartmentID`
  - `BranchID`
  - `JobTitle`
  - `EmploymentStatus`
  - `SupervisorID` optional
- Response:
  - `201 Created`
- Example:
  - [applicant-convert.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/applicant-convert.body.txt)

## Deployments

### `GET /deployments`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Query:
  - `EmployeeID`
  - `BranchID`
- Response:
  - `200 OK`
- Example:
  - [deployments-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/deployments-index.body.txt)

### `POST /deployments`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`
- Request JSON:
  - `EmployeeID`
  - `BranchID`
  - `DeploymentSite`
  - `StartDate`
  - `EndDate` optional
  - `Reason` optional
- Response:
  - `201 Created`
- Example:
  - [deployments-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/deployments-store.body.txt)

### `GET /branches/{branchId}/deployments/current`
- Auth: `HR Administrator`, `Branch Manager`, `HR Officer`, `Auditor`
- Response:
  - `200 OK`
- Example:
  - [deployments-current.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/deployments-current.body.txt)

## User Accounts

### `GET /user-accounts`
### `GET /user-accounts/{id}`
### `POST /user-accounts`
### `PATCH /user-accounts/{id}`
### `DELETE /user-accounts/{id}`
### `POST /user-accounts/{id}/reset-password`
### `PATCH /user-accounts/{id}/activate`
### `PATCH /user-accounts/{id}/deactivate`
- Auth: `HR Administrator`
- Purpose: Account lifecycle management.
- `POST` request JSON:
  - `EmployeeID` optional
  - `Username`
  - `RoleID`
  - either `password` + `password_confirmation` or `PasswordHash`
- `PATCH` request JSON:
  - same as create plus `AccountStatus`
- `reset-password` request JSON:
  - `password`
  - `password_confirmation`
- Sample responses:
  - [user-accounts-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/user-accounts-store.body.txt)
  - [user-accounts-show.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/user-accounts-show.body.txt)
  - [user-accounts-update.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/user-accounts-update.body.txt)
  - [user-accounts-reset-password.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/user-accounts-reset-password.body.txt)

## Notifications

### `GET /notifications`
- Auth: Bearer token
- Purpose: List notifications for the authenticated user.
- Response:
  - `200 OK` expected
- Live evidence:
  - [notifications-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/notifications-index.body.txt)

### `PATCH /notifications/{id}/read`
- Auth: Bearer token for the notification recipient
- Purpose: Mark one notification as read.
- Response:
  - `200 OK`
- Example:
  - [notifications-mark-read.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/notifications-mark-read.body.txt)

## Audit Logs

### `GET /audit-logs`
- Auth: `HR Administrator`
- Purpose: Read-only audit log list.
- Response:
  - `200 OK`
- Example:
  - [audit-logs-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/audit-logs-index.body.txt)

### `POST /audit-logs`
- Auth: `HR Administrator`
- Purpose: Intentionally blocked.
- Response:
  - `405 Method Not Allowed`
- Example:
  - [audit-logs-store-method-not-allowed.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/audit-logs-store-method-not-allowed.body.txt)

## Generic Admin Resources

These resources share the same admin-only CRUD pattern through `EntityController`:

- `GET /api/{resource}`
- `POST /api/{resource}`
- `GET /api/{resource}/{id}`
- `PATCH /api/{resource}/{id}`
- `DELETE /api/{resource}/{id}`

Resources using this pattern:
- `roles`
- `permissions`
- `role-permissions`
- `branches`
- `document-types`
- `trainings`
- `departments`
- `applicants`
- `notifications`
- `leave-balances`
- `additional-documents`
- `deployment-history`
- `employee-trainings`
- `assigned-allowances`
- `payroll-allowances`
- `assigned-deductions`
- `payroll-deductions`
- `audit-logs` is read-only

Live examples captured during the curl audit:
- [branches-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/branches-store.body.txt)
- [roles-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/roles-store.body.txt)
- [permissions-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/permissions-store.body.txt)
- [role-permissions-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/role-permissions-store.body.txt)
- [document-types-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/document-types-store.body.txt)
- [departments-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/departments-store.body.txt)
- [leave-balances-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/leave-balances-store.body.txt)
- [additional-documents-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/additional-documents-store.body.txt)
- [employee-trainings-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/employee-trainings-store.body.txt)
- [assigned-allowances-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/assigned-allowances-store.body.txt)
- [assigned-deductions-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/assigned-deductions-store.body.txt)
- [payroll-allowances-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/payroll-allowances-store.body.txt)
- [payroll-deductions-store.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/payroll-deductions-store.body.txt)
