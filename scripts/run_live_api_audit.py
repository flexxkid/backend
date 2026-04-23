from __future__ import annotations

import json
import os
import subprocess
import uuid
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
BASE_URL = os.environ.get("API_AUDIT_BASE_URL", "http://127.0.0.1:8000")
OUTPUT_DIR = ROOT / "storage" / "app" / "api-audit"
FIXTURES_DIR = OUTPUT_DIR / "fixtures"
RESPONSES_DIR = OUTPUT_DIR / "responses"
DOWNLOADS_DIR = OUTPUT_DIR / "downloads"
LOG_PATH = OUTPUT_DIR / "curl-log.md"
RUN_ID = uuid.uuid4().hex[:8]


def ensure_dirs() -> None:
    for path in (OUTPUT_DIR, FIXTURES_DIR, RESPONSES_DIR, DOWNLOADS_DIR):
        path.mkdir(parents=True, exist_ok=True)


def write_pdf_fixture(path: Path, title: str) -> None:
    path.write_text(
        "\n".join(
            [
                "%PDF-1.4",
                "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj",
                "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj",
                "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R >> endobj",
                "4 0 obj << /Length 44 >> stream",
                f"BT /F1 12 Tf 24 100 Td ({title}) Tj ET",
                "endstream endobj",
                "xref",
                "0 5",
                "0000000000 65535 f ",
                "trailer << /Size 5 /Root 1 0 R >>",
                "startxref",
                "0",
                "%%EOF",
            ]
        ),
        encoding="ascii",
    )


def quote(arg: str) -> str:
    if any(ch.isspace() for ch in arg) or '"' in arg or "$" in arg:
        return '"' + arg.replace('"', '\\"') + '"'
    return arg


def run(command: list[str]) -> None:
    subprocess.run(command, cwd=ROOT, check=True)


def seed() -> None:
    run(["php", "artisan", "db:seed", "--class=HrmsPermissionSeeder"])
    run(["php", "artisan", "db:seed", "--class=RecruitmentMockDataSeeder"])


def curl_request(
    name: str,
    method: str,
    path: str,
    *,
    headers: dict[str, str] | None = None,
    json_body: dict | None = None,
    form_fields: list[str] | None = None,
    download_file: Path | None = None,
    expect_json: bool = False,
) -> dict:
    slug = "".join(ch.lower() if ch.isalnum() else "-" for ch in name).strip("-")
    header_file = RESPONSES_DIR / f"{slug}.headers.txt"
    body_file = download_file or RESPONSES_DIR / f"{slug}.body.txt"
    url = f"{BASE_URL}{path}"

    args = ["curl.exe", "-sS", "-X", method, "-D", str(header_file), "-o", str(body_file)]

    for key, value in (headers or {}).items():
        args.extend(["-H", f"{key}: {value}"])

    if json_body is not None:
        args.extend(["-H", "Content-Type: application/json", "--data-raw", json.dumps(json_body, separators=(",", ":"))])

    for field in form_fields or []:
        args.extend(["-F", field])

    args.append(url)

    subprocess.run(args, cwd=ROOT, check=True)

    status_line = header_file.read_text(encoding="utf-8", errors="ignore").splitlines()[0]
    status_code = int(status_line.split(" ")[1])
    body_text = body_file.read_text(encoding="utf-8", errors="ignore") if body_file.exists() else ""

    with LOG_PATH.open("a", encoding="utf-8") as fh:
        fh.write(f"## {name}\n")
        fh.write("```bash\n")
        fh.write(" ".join(quote(arg) for arg in args) + "\n")
        fh.write("```\n\n")
        fh.write(f"Status: {status_code}\n\n")
        if download_file is None:
            fh.write(f"Response file: responses/{slug}.body.txt\n\n")
        else:
            fh.write(f"Download file: downloads/{download_file.name}\n\n")

    if expect_json and body_text:
        parsed = json.loads(body_text)
        body_file.write_text(json.dumps(parsed, indent=2), encoding="utf-8")
        body_text = body_file.read_text(encoding="utf-8")

    return {
        "status_code": status_code,
        "body_file": str(body_file),
        "header_file": str(header_file),
        "body_text": body_text,
    }


def load_json(result: dict) -> dict:
    return json.loads(Path(result["body_file"]).read_text(encoding="utf-8"))


def main() -> None:
    ensure_dirs()
    LOG_PATH.write_text("# Live Curl Audit\n", encoding="utf-8")

    fixtures = {
        "letter": FIXTURES_DIR / "letter-of-application.pdf",
        "certificate": FIXTURES_DIR / "highest-level-certificate.pdf",
        "cv": FIXTURES_DIR / "cv.pdf",
        "good_conduct": FIXTURES_DIR / "good-conduct.pdf",
        "national_id": FIXTURES_DIR / "national-id.pdf",
        "contract": FIXTURES_DIR / "contract.pdf",
    }

    write_pdf_fixture(fixtures["letter"], "API Audit Letter")
    write_pdf_fixture(fixtures["certificate"], "API Audit Certificate")
    write_pdf_fixture(fixtures["cv"], "API Audit CV")
    write_pdf_fixture(fixtures["good_conduct"], "API Audit Good Conduct")
    write_pdf_fixture(fixtures["national_id"], "API Audit National ID")
    write_pdf_fixture(fixtures["contract"], "API Audit Contract")

    seed()

    login = curl_request(
        "auth-login",
        "POST",
        "/api/auth/login",
        json_body={"Username": "seed.admin", "password": "secret123"},
        expect_json=True,
    )
    login_json = load_json(login)
    token = login_json["access_token"]
    auth_headers = {"Authorization": f"Bearer {token}", "Accept": "application/json"}

    curl_request("auth-me", "GET", "/api/user", headers=auth_headers, expect_json=True)
    curl_request("public-recruitment-index", "GET", "/api/recruitment", expect_json=True)

    branch = curl_request(
        "branches-store",
        "POST",
        "/api/branches",
        headers=auth_headers,
        json_body={
            "BranchName": f"API Audit Branch {RUN_ID}",
            "BranchLocation": "Nairobi West",
            "BranchPhone": "0701111111",
            "BranchEmail": f"api.audit.branch.{RUN_ID}@example.com",
        },
        expect_json=True,
    )
    branch_id = load_json(branch)["BranchID"]

    role = curl_request(
        "roles-store",
        "POST",
        "/api/roles",
        headers=auth_headers,
        json_body={"RoleName": f"API Audit Role {RUN_ID}", "RoleDescription": "Role created by live curl audit"},
        expect_json=True,
    )
    role_id = load_json(role)["RoleID"]

    roles_index = curl_request("roles-index", "GET", "/api/roles", headers=auth_headers, expect_json=True)
    employee_role_id = next(item["RoleID"] for item in load_json(roles_index)["data"] if item["RoleName"] == "Employee")

    permission = curl_request(
        "permissions-store",
        "POST",
        "/api/permissions",
        headers=auth_headers,
        json_body={"PermissionName": f"api-audit-permission-{RUN_ID}", "Description": "Permission created by live curl audit"},
        expect_json=True,
    )
    permission_id = load_json(permission)["PermissionID"]

    role_permission = curl_request(
        "role-permissions-store",
        "POST",
        "/api/role-permissions",
        headers=auth_headers,
        json_body={"RoleID": role_id, "PermissionID": permission_id},
        expect_json=True,
    )
    role_permission_id = load_json(role_permission)["RolePermissionID"]

    document_type = curl_request(
        "document-types-store",
        "POST",
        "/api/document-types",
        headers=auth_headers,
        json_body={"TypeName": "API Audit Document", "TypeDescription": "Document type for live curl audit"},
        expect_json=True,
    )
    document_type_id = load_json(document_type)["DocumentTypeID"]

    allowance = curl_request(
        "allowances-store",
        "POST",
        "/api/allowances",
        headers=auth_headers,
        json_body={"AllowanceName": f"API Audit Housing {RUN_ID}"},
        expect_json=True,
    )
    allowance_id = load_json(allowance)["AllowanceID"]

    deduction = curl_request(
        "deductions-store",
        "POST",
        "/api/deductions",
        headers=auth_headers,
        json_body={"DeductionName": f"API Audit PAYE {RUN_ID}", "Rate": 0.3},
        expect_json=True,
    )
    deduction_id = load_json(deduction)["DeductionID"]

    training = curl_request(
        "trainings-store",
        "POST",
        "/api/trainings",
        headers=auth_headers,
        json_body={
            "TrainingName": f"API Audit Orientation {RUN_ID}",
            "TrainingType": "Mandatory",
            "StartDate": "2026-04-20",
            "EndDate": "2026-04-21",
        },
        expect_json=True,
    )
    training_id = load_json(training)["TrainingID"]

    department = curl_request(
        "departments-store",
        "POST",
        "/api/departments",
        headers=auth_headers,
        json_body={
            "DepartmentName": f"API Audit Operations {RUN_ID}",
            "BranchID": branch_id,
            "DepartmentDescription": "Department created by live curl audit",
            "CreatedDate": "2026-04-23",
        },
        expect_json=True,
    )
    department_id = load_json(department)["DepartmentID"]

    recruitment = curl_request(
        "recruitment-store",
        "POST",
        "/api/recruitment",
        headers=auth_headers,
        json_body={
            "JobTitle": f"API Audit Guard {RUN_ID}",
            "DepartmentID": department_id,
            "VacancyStatus": "Open",
            "PostedDate": "2026-04-23",
        },
        expect_json=True,
    )
    recruitment_id = load_json(recruitment)["RecruitmentID"]

    employee = curl_request(
        "employees-store",
        "POST",
        "/api/employees",
        headers=auth_headers,
        json_body={
            "FirstName": "Audit",
            "LastName": "Employee",
            "DateOfBirth": "1995-03-10",
            "Email": f"api.audit.employee.{RUN_ID}@example.com",
            "PostalAddress": "P.O. Box 500",
            "PhoneNumber": "0722222222",
            "Gender": "Male",
            "JobTitle": "Security Officer",
            "NationalID": f"API-EMP-{RUN_ID}",
            "HireDate": "2026-04-23",
            "EmploymentStatus": "Active",
            "DepartmentID": department_id,
            "BranchID": branch_id,
        },
        expect_json=True,
    )
    employee_id = load_json(employee)["EmployeeID"]

    curl_request(
        "employees-update",
        "PATCH",
        f"/api/employees/{employee_id}",
        headers=auth_headers,
        json_body={
            "JobTitle": "Senior Security Officer",
            "EmploymentStatus": "Active",
            "Email": f"api.audit.employee.{RUN_ID}@example.com",
            "NationalID": f"API-EMP-{RUN_ID}",
            "HireDate": "2026-04-23",
            "DepartmentID": department_id,
            "BranchID": branch_id,
        },
        expect_json=True,
    )

    leave_balance = curl_request(
        "leave-balances-store",
        "POST",
        "/api/leave-balances",
        headers=auth_headers,
        json_body={"EmployeeID": employee_id, "LeaveType": "Annual", "TotalDays": 21, "UsedDays": 0},
        expect_json=True,
    )
    leave_balance_id = load_json(leave_balance)["LeaveBalanceID"]

    assigned_allowance = curl_request(
        "assigned-allowances-store",
        "POST",
        "/api/assigned-allowances",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "AllowanceID": allowance_id,
            "EffectiveDate": "2026-04-23",
            "Amount": 5000,
            "IsTaxable": True,
        },
        expect_json=True,
    )
    assigned_allowance_id = load_json(assigned_allowance)["AssignedAllowanceID"]

    assigned_deduction = curl_request(
        "assigned-deductions-store",
        "POST",
        "/api/assigned-deductions",
        headers=auth_headers,
        json_body={"EmployeeID": employee_id, "DeductionID": deduction_id, "EffectiveDate": "2026-04-23", "Amount": 1500},
        expect_json=True,
    )
    assigned_deduction_id = load_json(assigned_deduction)["AssignedDeductionID"]

    attendance = curl_request(
        "attendance-store",
        "POST",
        "/api/attendance",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "AttendanceDate": "2026-04-23",
            "Time_In": "08:00:00",
            "Time_Out": "17:00:00",
            "AttendanceStatus": "Present",
        },
        expect_json=True,
    )
    attendance_id = load_json(attendance)["AttendanceID"]

    curl_request(
        "attendance-update",
        "PATCH",
        f"/api/attendance/{attendance_id}",
        headers=auth_headers,
        json_body={"AttendanceStatus": "Late", "Time_In": "08:10:00", "Time_Out": "17:05:00"},
        expect_json=True,
    )

    leave = curl_request(
        "leave-store",
        "POST",
        "/api/leave",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "LeaveType": "Annual",
            "StartDate": "2026-05-05",
            "EndDate": "2026-05-07",
            "Reason": "Family event",
        },
        expect_json=True,
    )
    leave_id = load_json(leave)["LeaveID"]

    curl_request(
        "leave-approve",
        "PATCH",
        f"/api/leave/{leave_id}/approve",
        headers=auth_headers,
        json_body={"status": "Approved"},
        expect_json=True,
    )

    evaluation = curl_request(
        "performance-store",
        "POST",
        "/api/performance",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "EvaluationPeriod": "Q2-2026",
            "Score": 88,
            "Comments": "Reliable performance during audit cycle",
        },
        expect_json=True,
    )
    evaluation_id = load_json(evaluation)["EvaluationID"]

    curl_request(
        "training-enrol",
        "POST",
        f"/api/training/{employee_id}/enrol",
        headers=auth_headers,
        json_body={"TrainingID": training_id, "CompletionStatus": "Completed"},
        expect_json=True,
    )

    deployment = curl_request(
        "deployments-store",
        "POST",
        "/api/deployments",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "BranchID": branch_id,
            "DeploymentSite": "Audit Site A",
            "StartDate": "2026-04-23",
            "Reason": "Initial deployment",
        },
        expect_json=True,
    )
    deployment_id = load_json(deployment)["DeploymentID"]

    user_account = curl_request(
        "user-accounts-store",
        "POST",
        "/api/user-accounts",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "Username": f"api.audit.employee.{RUN_ID}",
            "password": "secret1234",
            "password_confirmation": "secret1234",
            "RoleID": employee_role_id,
        },
        expect_json=True,
    )
    user_id = load_json(user_account)["UserID"]

    notification = curl_request(
        "notifications-store",
        "POST",
        "/api/notifications",
        headers=auth_headers,
        json_body={
            "RecipientUserID": user_id,
            "Title": "API Audit Notification",
            "Message": "This notification was created by the live curl audit.",
            "NotificationType": "api-audit",
            "ReferenceTable": "Employee",
            "ReferenceID": employee_id,
            "IsRead": False,
        },
        expect_json=True,
    )
    notification_id = load_json(notification)["NotificationID"]

    user_login = curl_request(
        "user-auth-login",
        "POST",
        "/api/auth/login",
        json_body={"Username": f"api.audit.employee.{RUN_ID}", "password": "secret1234"},
        expect_json=True,
    )
    user_token = load_json(user_login)["access_token"]
    user_headers = {"Authorization": f"Bearer {user_token}", "Accept": "application/json"}

    upload_endpoint = curl_request(
        "upload-endpoint-store",
        "POST",
        "/api/upload",
        headers={"Authorization": f"Bearer {token}"},
        form_fields=[
            f"EmployeeID={employee_id}",
            f"DocumentTypeID={document_type_id}",
            "Description=API upload endpoint document",
            f"file=@{fixtures['national_id']};type=application/pdf",
        ],
        expect_json=True,
    )
    document_id_from_upload = load_json(upload_endpoint)["document"]["DocumentID"]

    employee_document = curl_request(
        "employee-document-store",
        "POST",
        f"/api/employees/{employee_id}/documents",
        headers={"Authorization": f"Bearer {token}"},
        form_fields=[
            f"DocumentTypeID={document_type_id}",
            "Description=API employee document",
            "ExpiryDate=2027-04-23",
            f"file=@{fixtures['contract']};type=application/pdf",
        ],
        expect_json=True,
    )
    employee_document_id = load_json(employee_document)["DocumentID"]

    public_apply = curl_request(
        "recruitment-apply-public",
        "POST",
        f"/api/recruitment/{recruitment_id}/apply",
        headers={"Accept": "application/json"},
        form_fields=[
            "FirstName=Public",
            "LastName=Applicant",
            f"Email=public.applicant.{RUN_ID}@example.com",
            "Address=P.O. Box 600",
            "PhoneNumber=0733333333",
            "Gender=Female",
            f"NationalID=API-APP-{RUN_ID}",
            f"LetterOfApplication=@{fixtures['letter']};type=application/pdf",
            f"HighestLevelCertificate=@{fixtures['certificate']};type=application/pdf",
            f"CV=@{fixtures['cv']};type=application/pdf",
            f"GoodConduct=@{fixtures['good_conduct']};type=application/pdf",
        ],
        expect_json=True,
    )
    applicant_id = load_json(public_apply)["ApplicationID"]

    convert = curl_request(
        "applicant-convert",
        "POST",
        f"/api/applicants/{applicant_id}/convert",
        headers=auth_headers,
        json_body={
            "HireDate": "2026-05-01",
            "DepartmentID": department_id,
            "BranchID": branch_id,
            "JobTitle": "Guard Recruit",
            "EmploymentStatus": "Active",
            "SupervisorID": employee_id,
        },
        expect_json=True,
    )
    converted_employee_id = load_json(convert)["EmployeeID"]

    payroll = curl_request(
        "payroll-store",
        "POST",
        "/api/payroll",
        headers=auth_headers,
        json_body={"EmployeeID": employee_id, "PayPeriod": "2026-05", "BasicSalary": 50000, "PaymentDate": "2026-05-31"},
        expect_json=True,
    )
    payroll_id = load_json(payroll)["PayrollID"]

    extra_allowance = curl_request(
        "allowances-store-extra",
        "POST",
        "/api/allowances",
        headers=auth_headers,
        json_body={"AllowanceName": f"API Audit Transport {RUN_ID}"},
        expect_json=True,
    )
    extra_allowance_id = load_json(extra_allowance)["AllowanceID"]

    extra_assigned_allowance = curl_request(
        "assigned-allowances-store-extra",
        "POST",
        "/api/assigned-allowances",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "AllowanceID": extra_allowance_id,
            "EffectiveDate": "2026-04-24",
            "Amount": 750,
            "IsTaxable": False,
        },
        expect_json=True,
    )
    extra_assigned_allowance_id = load_json(extra_assigned_allowance)["AssignedAllowanceID"]

    payroll_allowance = curl_request(
        "payroll-allowances-store",
        "POST",
        "/api/payroll-allowances",
        headers=auth_headers,
        json_body={"PayrollID": payroll_id, "AssignedAllowanceID": extra_assigned_allowance_id},
        expect_json=True,
    )
    payroll_allowance_id = load_json(payroll_allowance)["PayrollAllowanceID"]

    extra_deduction = curl_request(
        "deductions-store-extra",
        "POST",
        "/api/deductions",
        headers=auth_headers,
        json_body={"DeductionName": f"API Audit Insurance {RUN_ID}", "Rate": 0.05},
        expect_json=True,
    )
    extra_deduction_id = load_json(extra_deduction)["DeductionID"]

    extra_assigned_deduction = curl_request(
        "assigned-deductions-store-extra",
        "POST",
        "/api/assigned-deductions",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "DeductionID": extra_deduction_id,
            "EffectiveDate": "2026-04-24",
            "Amount": 250,
        },
        expect_json=True,
    )
    extra_assigned_deduction_id = load_json(extra_assigned_deduction)["AssignedDeductionID"]

    payroll_deduction = curl_request(
        "payroll-deductions-store",
        "POST",
        "/api/payroll-deductions",
        headers=auth_headers,
        json_body={"PayrollID": payroll_id, "AssignedDeductionID": extra_assigned_deduction_id},
        expect_json=True,
    )
    payroll_deduction_id = load_json(payroll_deduction)["PayrollDeductionID"]

    additional_document = curl_request(
        "additional-documents-store",
        "POST",
        "/api/additional-documents",
        headers=auth_headers,
        json_body={
            "EmployeeID": employee_id,
            "DocumentTypeID": document_type_id,
            "Document": "manual/audit-reference.pdf",
            "Description": "Manual additional document reference",
            "ExpiryDate": "2027-04-23",
            "UploadedBy": employee_id,
        },
        expect_json=True,
    )
    additional_document_id = load_json(additional_document)["DocumentID"]

    employee_training = curl_request(
        "employee-trainings-store",
        "POST",
        "/api/employee-trainings",
        headers=auth_headers,
        json_body={"EmployeeID": converted_employee_id, "TrainingID": training_id, "CompletionStatus": "Enrolled"},
        expect_json=True,
    )
    employee_training_id = load_json(employee_training)["EmployeeTrainingID"]

    curl_request("employees-index", "GET", f"/api/employees?search=audit&DepartmentID={department_id}&BranchID={branch_id}&EmploymentStatus=Active", headers=auth_headers, expect_json=True)
    curl_request("employees-show", "GET", f"/api/employees/{employee_id}", headers=auth_headers, expect_json=True)
    curl_request("employees-export-csv", "GET", "/api/employees?export=csv", headers=auth_headers, download_file=DOWNLOADS_DIR / "employees.csv")
    curl_request("employees-export-pdf", "GET", "/api/employees?export=pdf", headers=auth_headers, download_file=DOWNLOADS_DIR / "employees.pdf")
    curl_request("documents-download", "GET", f"/api/documents/{employee_document_id}/download", headers=auth_headers, expect_json=True)
    curl_request("leave-index", "GET", f"/api/leave?EmployeeID={employee_id}&LeaveStatus=Approved", headers=auth_headers, expect_json=True)
    curl_request("attendance-index", "GET", f"/api/attendance?EmployeeID={employee_id}&from=2026-04-01&to=2026-04-30", headers=auth_headers, expect_json=True)
    curl_request("payroll-index", "GET", f"/api/payroll?EmployeeID={employee_id}&PayPeriod=2026-05", headers=auth_headers, expect_json=True)
    curl_request("payroll-show", "GET", f"/api/payroll/{payroll_id}", headers=auth_headers, expect_json=True)
    curl_request("performance-index", "GET", "/api/performance", headers=auth_headers, expect_json=True)
    curl_request("training-index", "GET", "/api/training", headers=auth_headers, expect_json=True)
    curl_request("training-outstanding", "GET", "/api/training/outstanding", headers=auth_headers, expect_json=True)
    curl_request("deployments-index", "GET", f"/api/deployments?EmployeeID={employee_id}&BranchID={branch_id}", headers=auth_headers, expect_json=True)
    curl_request("deployments-current", "GET", f"/api/branches/{branch_id}/deployments/current", headers=auth_headers, expect_json=True)
    curl_request("user-accounts-index", "GET", "/api/user-accounts", headers=auth_headers, expect_json=True)
    curl_request("user-accounts-show", "GET", f"/api/user-accounts/{user_id}", headers=auth_headers, expect_json=True)
    curl_request(
        "user-accounts-update",
        "PATCH",
        f"/api/user-accounts/{user_id}",
        headers=auth_headers,
        json_body={"EmployeeID": employee_id, "Username": f"api.audit.employee.{RUN_ID}", "RoleID": employee_role_id, "AccountStatus": "active"},
        expect_json=True,
    )
    curl_request(
        "user-accounts-reset-password",
        "POST",
        f"/api/user-accounts/{user_id}/reset-password",
        headers=auth_headers,
        json_body={"password": "secret5678", "password_confirmation": "secret5678"},
        expect_json=True,
    )
    curl_request("user-accounts-deactivate", "PATCH", f"/api/user-accounts/{user_id}/deactivate", headers=auth_headers, expect_json=True)
    curl_request("user-accounts-activate", "PATCH", f"/api/user-accounts/{user_id}/activate", headers=auth_headers, expect_json=True)
    curl_request("notifications-index", "GET", "/api/notifications", headers=user_headers, expect_json=True)
    curl_request("notifications-mark-read", "PATCH", f"/api/notifications/{notification_id}/read", headers=user_headers, expect_json=True)
    curl_request("audit-logs-index", "GET", "/api/audit-logs", headers=auth_headers, expect_json=True)
    curl_request(
        "audit-logs-store-method-not-allowed",
        "POST",
        "/api/audit-logs",
        headers=auth_headers,
        json_body={"Action": "manual", "AffectedTable": "Employee"},
        expect_json=True,
    )
    curl_request("branches-show", "GET", f"/api/branches/{branch_id}", headers=auth_headers, expect_json=True)
    curl_request(
        "branches-update",
        "PATCH",
        f"/api/branches/{branch_id}",
        headers=auth_headers,
        json_body={
            "BranchName": f"API Audit Branch Updated {RUN_ID}",
            "BranchLocation": "Nairobi West",
            "BranchPhone": "0701111111",
            "BranchEmail": f"api.audit.branch.{RUN_ID}@example.com",
        },
        expect_json=True,
    )
    curl_request("roles-show", "GET", f"/api/roles/{role_id}", headers=auth_headers, expect_json=True)
    curl_request("permissions-show", "GET", f"/api/permissions/{permission_id}", headers=auth_headers, expect_json=True)
    curl_request("document-types-show", "GET", f"/api/document-types/{document_type_id}", headers=auth_headers, expect_json=True)
    curl_request("allowances-show", "GET", f"/api/allowances/{allowance_id}", headers=auth_headers, expect_json=True)
    curl_request("allowances-update", "PATCH", f"/api/allowances/{allowance_id}", headers=auth_headers, json_body={"AllowanceName": f"API Audit Housing Updated {RUN_ID}"}, expect_json=True)
    curl_request("deductions-show", "GET", f"/api/deductions/{deduction_id}", headers=auth_headers, expect_json=True)
    curl_request("deductions-update", "PATCH", f"/api/deductions/{deduction_id}", headers=auth_headers, json_body={"DeductionName": f"API Audit PAYE Updated {RUN_ID}", "Rate": 0.25}, expect_json=True)
    curl_request("trainings-show", "GET", f"/api/trainings/{training_id}", headers=auth_headers, expect_json=True)
    curl_request("departments-show", "GET", f"/api/departments/{department_id}", headers=auth_headers, expect_json=True)
    curl_request("applicants-show", "GET", f"/api/applicants/{applicant_id}", headers=auth_headers, expect_json=True)
    curl_request("leave-balances-show", "GET", f"/api/leave-balances/{leave_balance_id}", headers=auth_headers, expect_json=True)
    curl_request("additional-documents-show", "GET", f"/api/additional-documents/{additional_document_id}", headers=auth_headers, expect_json=True)
    curl_request("deployment-history-show", "GET", f"/api/deployment-history/{deployment_id}", headers=auth_headers, expect_json=True)
    curl_request("employee-trainings-show", "GET", f"/api/employee-trainings/{employee_training_id}", headers=auth_headers, expect_json=True)
    curl_request("assigned-allowances-show", "GET", f"/api/assigned-allowances/{assigned_allowance_id}", headers=auth_headers, expect_json=True)
    curl_request("assigned-deductions-show", "GET", f"/api/assigned-deductions/{assigned_deduction_id}", headers=auth_headers, expect_json=True)
    curl_request("payroll-allowances-show", "GET", f"/api/payroll-allowances/{payroll_allowance_id}", headers=auth_headers, expect_json=True)
    curl_request("payroll-deductions-show", "GET", f"/api/payroll-deductions/{payroll_deduction_id}", headers=auth_headers, expect_json=True)
    curl_request("user-accounts-destroy", "DELETE", f"/api/user-accounts/{user_id}", headers=auth_headers, expect_json=True)
    curl_request("employees-destroy", "DELETE", f"/api/employees/{converted_employee_id}", headers=auth_headers, expect_json=True)
    curl_request("allowances-destroy", "DELETE", f"/api/allowances/{allowance_id}", headers=auth_headers, expect_json=True)
    curl_request("allowances-destroy-extra", "DELETE", f"/api/allowances/{extra_allowance_id}", headers=auth_headers, expect_json=True)
    curl_request("deductions-destroy", "DELETE", f"/api/deductions/{deduction_id}", headers=auth_headers, expect_json=True)
    curl_request("deductions-destroy-extra", "DELETE", f"/api/deductions/{extra_deduction_id}", headers=auth_headers, expect_json=True)
    curl_request("user-auth-logout", "POST", "/api/auth/logout", headers=user_headers, expect_json=True)
    curl_request("auth-logout", "POST", "/api/auth/logout", headers=auth_headers, expect_json=True)

    summary = {
        "generated_at": subprocess.check_output(["powershell", "-NoProfile", "-Command", "Get-Date -Format s"], text=True).strip(),
        "base_url": BASE_URL,
        "admin_username": "seed.admin",
        "run_id": RUN_ID,
        "output_directory": str(OUTPUT_DIR),
        "created_ids": {
            "BranchID": branch_id,
            "RoleID": role_id,
            "PermissionID": permission_id,
            "RolePermissionID": role_permission_id,
            "DocumentTypeID": document_type_id,
            "EmployeeID": employee_id,
            "RecruitmentID": recruitment_id,
            "ApplicantID": applicant_id,
            "ConvertedEmployeeID": converted_employee_id,
            "LeaveID": leave_id,
            "AttendanceID": attendance_id,
            "PayrollID": payroll_id,
            "EvaluationID": evaluation_id,
            "DeploymentID": deployment_id,
            "UserID": user_id,
            "NotificationID": notification_id,
            "EmployeeDocumentID": employee_document_id,
            "UploadDocumentID": document_id_from_upload,
        },
    }
    (OUTPUT_DIR / "summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(f"Live curl audit complete. Artifacts saved to {OUTPUT_DIR}")


if __name__ == "__main__":
    main()
