# HRMS Spec Alignment Audit

Source document reviewed:
- [hrms_features_implementation.pdf](C:/Users/PC/Downloads/hrms_features_implementation.pdf)

Live audit evidence:
- [curl-log.md](/C:/Users/PC/Projects/backend/storage/app/api-audit/curl-log.md)
- [summary.json](/C:/Users/PC/Projects/backend/storage/app/api-audit/summary.json)
- [hrms-api-reference.md](/C:/Users/PC/Projects/backend/docs/hrms-api-reference.md)

## Aligned Areas

### Authentication and token flow
- `POST /auth/login`, `GET /user`, and `POST /auth/logout` behave as Sanctum bearer-token endpoints.
- Live evidence:
  - [auth-login.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/auth-login.body.txt)
  - [auth-me.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/auth-me.body.txt)

### Role-protected API structure
- Admin-only modules such as payroll, user accounts, and generic CRUD resources are protected.
- Branch- and HR-level modules such as leave, attendance, deployments, and training use route middleware consistent with the documented role model.

### Employee profile management
- Employee create, view, update, search, CSV export, PDF export, and deactivate flows are implemented and tested.
- Full names are normalized from first and last names into the existing `FullName` column.

### Recruitment and applicant management
- Public recruitment listing is available.
- Applicants can apply against a posting and upload real files.
- Applicants can be converted into employees.

### Document upload and storage paths
- Employee documents and recruitment application files are stored as real files and their saved paths are written to the database.
- Download URL generation is implemented.

### Leave lifecycle
- Leave balances are checked before submission.
- Approval updates balances and records approver metadata.

### Attendance capture and filtering
- Attendance create and list endpoints work with employee/date filters.

### Payroll processing
- Payroll processing combines salary, assigned allowances, and deductions and returns linked payroll details.

### Training and deployment
- Training enrolment and outstanding training reporting work.
- Deployment history creation and “currently deployed” lookup work.

### User accounts
- Account creation, update, reset password, activate, deactivate, and destroy/deactivate flows all respond correctly.

### Audit log immutability
- `GET /audit-logs` works.
- `POST /audit-logs` is blocked with `405`.

## Partial Or Unverified Areas

### Backblaze B2 cloud storage
- The document expects production document storage in Backblaze B2 with signed URLs.
- The codebase supports a `b2` disk, but this local live audit ran without real B2 credentials, so the environment-level B2 integration was not validated against the actual cloud service.

### Reverb, Redis queue, Mailgun, and scheduled expiry alerts
- The document expects in-system notifications plus realtime websocket delivery, queued mail delivery, and scheduled expiry reminders.
- The current live audit verified database-backed notifications and document/leave notification creation only.
- Websocket broadcast, queued mail, and scheduled expiry alert execution were not validated here.

### Search/filter breadth
- The document mentions employee-number searches and broader filter combinations.
- The current employee endpoint supports `search`, `DepartmentID`, `BranchID`, and `EmploymentStatus`, and the search covers `FullName`, `NationalID`, `Email`, and `JobTitle`.
- An explicit employee-number filter is not separately exposed in the live route set.

### Read-event audit logging
- The specification mentions logging view/read actions.
- The current implementation clearly records mutating actions and auth actions.
- The live audit did not find evidence that ordinary `GET` reads are written to `AuditLog`.

## Known Live Gaps From The Curl Audit

### Attendance update still behaved stricter than the spec in the live server run
- The live curl audit on port `8000` returned `422` for `PATCH /attendance/{id}` when only `Time_In`, `Time_Out`, and `AttendanceStatus` were provided.
- Evidence:
  - [attendance-update.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/attendance-update.body.txt)
- The controller code has already been patched to make `AttendanceDate` optional on update:
  - [AttendanceController.php](/C:/Users/PC/Projects/backend/app/Http/Controllers/AttendanceController.php:30)
- This should be revalidated after restarting the long-running local server process.

### Notifications index needs one more clean role-based recheck
- `PATCH /notifications/{id}/read` succeeded for the actual notification recipient.
- `GET /notifications` returned `403` in the captured live run for the audit-created user, which is unexpected because the route itself is only behind `auth:sanctum`.
- Evidence:
  - [notifications-index.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/notifications-index.body.txt)
  - [notifications-mark-read.body.txt](/C:/Users/PC/Projects/backend/storage/app/api-audit/responses/notifications-mark-read.body.txt)
- This should be rechecked after restarting the local API server and rerunning the live audit.

## Validation Summary

- Live curl audit completed and saved artifacts under:
  - [storage/app/api-audit](/C:/Users/PC/Projects/backend/storage/app/api-audit)
- Automated test suite status:
  - `43 passed (234 assertions)`
