<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Branch', function (Blueprint $table) {
            $table->increments('BranchID');
            $table->string('BranchName', 150)->unique();
            $table->string('BranchLocation', 255)->nullable();
            $table->string('BranchPhone', 20)->nullable();
            $table->string('BranchEmail', 150)->nullable();
        });

        Schema::create('Role', function (Blueprint $table) {
            $table->increments('RoleID');
            $table->string('RoleName', 100);
            $table->text('RoleDescription')->nullable();
        });

        Schema::create('Permission', function (Blueprint $table) {
            $table->increments('PermissionID');
            $table->string('PermissionName', 100)->unique();
            $table->text('Description')->nullable();
        });

        Schema::create('DocumentType', function (Blueprint $table) {
            $table->increments('DocumentTypeID');
            $table->string('TypeName', 100);
            $table->text('TypeDescription')->nullable();
        });

        Schema::create('Allowances', function (Blueprint $table) {
            $table->increments('AllowanceID');
            $table->string('AllowanceName', 150);
        });

        Schema::create('Deductions', function (Blueprint $table) {
            $table->increments('DeductionID');
            $table->string('DeductionName', 150);
            $table->decimal('Rate', 10, 4)->nullable();
        });

        Schema::create('Training', function (Blueprint $table) {
            $table->increments('TrainingID');
            $table->string('TrainingName', 200);
            $table->string('TrainingType', 100)->nullable();
            $table->date('StartDate')->nullable();
            $table->date('EndDate')->nullable();
        });

        Schema::create('Department', function (Blueprint $table) {
            $table->increments('DepartmentID');
            $table->string('DepartmentName', 150);
            $table->unsignedInteger('HODID')->nullable();
            $table->unsignedInteger('BranchID')->nullable();
            $table->text('DepartmentDescription')->nullable();
            $table->date('CreatedDate')->nullable();
        });

        Schema::create('Recruitment', function (Blueprint $table) {
            $table->increments('RecruitmentID');
            $table->string('JobTitle', 150);
            $table->unsignedInteger('DepartmentID')->nullable();
            $table->string('VacancyStatus', 50)->nullable();
            $table->date('PostedDate')->nullable();
        });

        Schema::create('Applicant', function (Blueprint $table) {
            $table->increments('ApplicationID');
            $table->string('FullName', 200);
            $table->date('DateOfBirth')->nullable();
            $table->string('Email', 150)->nullable();
            $table->string('Address', 255)->nullable();
            $table->string('PhoneNumber', 20)->nullable();
            $table->string('Gender', 20)->nullable();
            $table->string('LetterOfApplication', 500)->nullable();
            $table->string('HighestLevelCertificate', 255)->nullable();
            $table->string('CV', 500)->nullable();
            $table->string('ApplicationStatus', 50)->nullable();
            $table->string('GoodConduct', 500)->nullable();
            $table->string('NationalID', 50)->unique();
            $table->unsignedInteger('RecruitmentID')->nullable();
        });

        Schema::create('Employee', function (Blueprint $table) {
            $table->increments('EmployeeID');
            $table->string('FullName', 200);
            $table->date('DateOfBirth')->nullable();
            $table->string('Email', 150)->unique();
            $table->string('PostalAddress', 255)->nullable();
            $table->string('PhoneNumber', 20)->nullable();
            $table->string('Gender', 20)->nullable();
            $table->string('JobTitle', 150)->nullable();
            $table->string('LetterOfApplication', 500)->nullable();
            $table->string('HighestLevelCertificate', 255)->nullable();
            $table->string('CV', 500)->nullable();
            $table->string('ApplicationStatus', 50)->nullable();
            $table->string('GoodConduct', 500)->nullable();
            $table->string('NationalID', 50)->unique();
            $table->date('HireDate');
            $table->string('EmploymentStatus', 50)->nullable();
            $table->unsignedInteger('DepartmentID')->nullable();
            $table->unsignedInteger('SupervisorID')->nullable();
            $table->unsignedInteger('BranchID')->nullable();
        });

        Schema::create('UserAccount', function (Blueprint $table) {
            $table->increments('UserID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->string('Username', 100)->unique();
            $table->string('PasswordHash', 255);
            $table->unsignedInteger('RoleID')->nullable();
            $table->string('AccountStatus', 50)->nullable();
            $table->dateTime('LastLogin')->nullable();
        });

        Schema::create('Notifications', function (Blueprint $table) {
            $table->increments('NotificationID');
            $table->unsignedInteger('RecipientUserID')->nullable();
            $table->string('Title', 200);
            $table->text('Message');
            $table->string('NotificationType', 100)->nullable();
            $table->string('ReferenceTable', 100)->nullable();
            $table->unsignedInteger('ReferenceID')->nullable();
            $table->boolean('IsRead')->default(false);
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('ReadAt')->nullable();
        });

        Schema::create('RolePermission', function (Blueprint $table) {
            $table->increments('RolePermissionID');
            $table->unsignedInteger('RoleID');
            $table->unsignedInteger('PermissionID');
            $table->unique(['RoleID', 'PermissionID']);
        });

        Schema::create('Attendance', function (Blueprint $table) {
            $table->increments('AttendanceID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->date('AttendanceDate');
            $table->time('Time_In')->nullable();
            $table->time('Time_Out')->nullable();
            $table->string('AttendanceStatus', 50)->nullable();
        });

        Schema::create('LeaveRequest', function (Blueprint $table) {
            $table->increments('LeaveID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->string('LeaveType', 100);
            $table->date('StartDate');
            $table->date('EndDate');
            $table->text('Reason')->nullable();
            $table->string('LeaveStatus', 50)->nullable();
            $table->unsignedInteger('ApprovedBy')->nullable();
        });

        Schema::create('LeaveBalance', function (Blueprint $table) {
            $table->increments('LeaveBalanceID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->string('LeaveType', 100);
            $table->integer('TotalDays')->default(0);
            $table->integer('UsedDays')->default(0);
            $table->integer('RemainingDays')->default(0);
        });

        Schema::create('AdditionalDocuments', function (Blueprint $table) {
            $table->increments('DocumentID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->unsignedInteger('DocumentTypeID')->nullable();
            $table->string('Document', 500);
            $table->text('Description')->nullable();
            $table->date('ExpiryDate')->nullable();
            $table->dateTime('UploadDate')->nullable();
            $table->unsignedInteger('UploadedBy')->nullable();
        });

        Schema::create('DeploymentHistory', function (Blueprint $table) {
            $table->increments('DeploymentID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->unsignedInteger('BranchID')->nullable();
            $table->string('DeploymentSite', 255)->nullable();
            $table->date('StartDate');
            $table->date('EndDate')->nullable();
            $table->text('Reason')->nullable();
            $table->unsignedInteger('DeployedBy')->nullable();
        });

        Schema::create('EmployeeTraining', function (Blueprint $table) {
            $table->increments('EmployeeTrainingID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->unsignedInteger('TrainingID')->nullable();
            $table->string('CompletionStatus', 50)->nullable();
            $table->unique(['EmployeeID', 'TrainingID']);
        });

        Schema::create('PerformanceEvaluation', function (Blueprint $table) {
            $table->increments('EvaluationID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->unsignedInteger('EvaluatorID')->nullable();
            $table->string('EvaluationPeriod', 100)->nullable();
            $table->integer('Score')->nullable();
            $table->text('Comments')->nullable();
        });

        Schema::create('Payroll', function (Blueprint $table) {
            $table->increments('PayrollID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->string('PayPeriod', 100)->nullable();
            $table->decimal('BasicSalary', 15, 2)->default(0);
            $table->decimal('NetSalary', 15, 2)->default(0);
            $table->date('PaymentDate')->nullable();
        });

        Schema::create('AssignedAllowance', function (Blueprint $table) {
            $table->increments('AssignedAllowanceID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->unsignedInteger('AllowanceID')->nullable();
            $table->date('EffectiveDate')->nullable();
            $table->date('EndDate')->nullable();
            $table->boolean('IsTaxable')->default(false);
            $table->decimal('Amount', 15, 2)->default(0);
        });

        Schema::create('PayrollAllowance', function (Blueprint $table) {
            $table->increments('PayrollAllowanceID');
            $table->unsignedInteger('PayrollID');
            $table->unsignedInteger('AssignedAllowanceID');
            $table->unique(['PayrollID', 'AssignedAllowanceID']);
        });

        Schema::create('AssignedDeduction', function (Blueprint $table) {
            $table->increments('AssignedDeductionID');
            $table->unsignedInteger('EmployeeID')->nullable();
            $table->unsignedInteger('DeductionID')->nullable();
            $table->date('EffectiveDate')->nullable();
            $table->date('EndDate')->nullable();
            $table->decimal('Amount', 15, 2)->default(0);
        });

        Schema::create('PayrollDeduction', function (Blueprint $table) {
            $table->increments('PayrollDeductionID');
            $table->unsignedInteger('PayrollID');
            $table->unsignedInteger('AssignedDeductionID');
            $table->unique(['PayrollID', 'AssignedDeductionID']);
        });

        Schema::create('AuditLog', function (Blueprint $table) {
            $table->increments('AuditID');
            $table->unsignedInteger('UserID')->nullable();
            $table->string('Username', 100)->nullable();
            $table->string('Action', 100);
            $table->string('AffectedTable', 100);
            $table->unsignedInteger('AffectedRecordID')->nullable();
            $table->json('OldValues')->nullable();
            $table->json('NewValues')->nullable();
            $table->string('IPAddress', 45)->nullable();
            $table->dateTime('CreatedAt');
        });

        Schema::table('Department', function (Blueprint $table) {
            $table->foreign('BranchID')->references('BranchID')->on('Branch')->nullOnDelete();
        });

        Schema::table('Recruitment', function (Blueprint $table) {
            $table->foreign('DepartmentID')->references('DepartmentID')->on('Department')->nullOnDelete();
        });

        Schema::table('Applicant', function (Blueprint $table) {
            $table->foreign('RecruitmentID')->references('RecruitmentID')->on('Recruitment')->nullOnDelete();
        });

        Schema::table('Employee', function (Blueprint $table) {
            $table->foreign('DepartmentID')->references('DepartmentID')->on('Department')->nullOnDelete();
            $table->foreign('SupervisorID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('BranchID')->references('BranchID')->on('Branch')->nullOnDelete();
        });

        Schema::table('Department', function (Blueprint $table) {
            $table->foreign('HODID')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('UserAccount', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('RoleID')->references('RoleID')->on('Role')->nullOnDelete();
        });

        Schema::table('Notifications', function (Blueprint $table) {
            $table->foreign('RecipientUserID')->references('UserID')->on('UserAccount')->nullOnDelete();
        });

        Schema::table('RolePermission', function (Blueprint $table) {
            $table->foreign('RoleID')->references('RoleID')->on('Role')->cascadeOnDelete();
            $table->foreign('PermissionID')->references('PermissionID')->on('Permission')->cascadeOnDelete();
        });

        Schema::table('Attendance', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('LeaveRequest', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('ApprovedBy')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('LeaveBalance', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('AdditionalDocuments', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('DocumentTypeID')->references('DocumentTypeID')->on('DocumentType')->nullOnDelete();
            $table->foreign('UploadedBy')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('DeploymentHistory', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('BranchID')->references('BranchID')->on('Branch')->nullOnDelete();
            $table->foreign('DeployedBy')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('EmployeeTraining', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('TrainingID')->references('TrainingID')->on('Training')->nullOnDelete();
        });

        Schema::table('PerformanceEvaluation', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('EvaluatorID')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('Payroll', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
        });

        Schema::table('AssignedAllowance', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('AllowanceID')->references('AllowanceID')->on('Allowances')->nullOnDelete();
        });

        Schema::table('PayrollAllowance', function (Blueprint $table) {
            $table->foreign('PayrollID')->references('PayrollID')->on('Payroll')->cascadeOnDelete();
            $table->foreign('AssignedAllowanceID')->references('AssignedAllowanceID')->on('AssignedAllowance')->cascadeOnDelete();
        });

        Schema::table('AssignedDeduction', function (Blueprint $table) {
            $table->foreign('EmployeeID')->references('EmployeeID')->on('Employee')->nullOnDelete();
            $table->foreign('DeductionID')->references('DeductionID')->on('Deductions')->nullOnDelete();
        });

        Schema::table('PayrollDeduction', function (Blueprint $table) {
            $table->foreign('PayrollID')->references('PayrollID')->on('Payroll')->cascadeOnDelete();
            $table->foreign('AssignedDeductionID')->references('AssignedDeductionID')->on('AssignedDeduction')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'AuditLog',
            'PayrollDeduction',
            'AssignedDeduction',
            'PayrollAllowance',
            'AssignedAllowance',
            'Payroll',
            'PerformanceEvaluation',
            'EmployeeTraining',
            'DeploymentHistory',
            'AdditionalDocuments',
            'LeaveBalance',
            'LeaveRequest',
            'Attendance',
            'Notifications',
            'UserAccount',
            'Employee',
            'Applicant',
            'Recruitment',
            'Department',
            'Training',
            'Deductions',
            'Allowances',
            'DocumentType',
            'RolePermission',
            'Permission',
            'Role',
            'Branch',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};
