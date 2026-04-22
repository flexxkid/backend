<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AllowancesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeductionsController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PerformanceEvaluationController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserAccountController;
use App\Support\HrmsEntityRegistry;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/recruitment', [RecruitmentController::class, 'index']);
Route::post('/recruitment/{recruitmentId}/apply', [RecruitmentController::class, 'apply'])->whereNumber('recruitmentId');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('role:HR Administrator,HR Officer');
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])->whereNumber('id')->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');
    Route::match(['put', 'patch'], '/employees/{id}', [EmployeeController::class, 'update'])->whereNumber('id')->middleware('role:HR Administrator,HR Officer');
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])->whereNumber('id')->middleware('role:HR Administrator');

    Route::post('/employees/{employeeId}/documents', [DocumentController::class, 'store'])->whereNumber('employeeId')->middleware('role:HR Administrator,HR Officer');
    Route::get('/documents/{documentId}/download', [DocumentController::class, 'show'])->whereNumber('documentId')->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');
    Route::post('/upload', [UploadController::class, 'uploadDocument'])->middleware('role:HR Administrator,HR Officer');

    Route::get('/leave', [LeaveController::class, 'index'])->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');
    Route::post('/leave', [LeaveController::class, 'store'])->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');
    Route::patch('/leave/{id}/approve', [LeaveController::class, 'approve'])->whereNumber('id')->middleware('role:HR Administrator,Branch Manager');

    Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');
    Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('role:HR Administrator,Branch Manager,HR Officer');
    Route::patch('/attendance/{id}', [AttendanceController::class, 'update'])->whereNumber('id')->middleware('role:HR Administrator,Branch Manager,HR Officer');

    Route::get('/payroll', [PayrollController::class, 'index'])->middleware('role:HR Administrator');
    Route::post('/payroll', [PayrollController::class, 'store'])->middleware('role:HR Administrator');
    Route::get('/payroll/{id}', [PayrollController::class, 'show'])->whereNumber('id')->middleware('role:HR Administrator');

    Route::get('/allowances', [AllowancesController::class, 'index'])->middleware('role:HR Administrator');
    Route::post('/allowances', [AllowancesController::class, 'store'])->middleware('role:HR Administrator');
    Route::get('/allowances/{id}', [AllowancesController::class, 'show'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::match(['put', 'patch'], '/allowances/{id}', [AllowancesController::class, 'update'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::delete('/allowances/{id}', [AllowancesController::class, 'destroy'])->whereNumber('id')->middleware('role:HR Administrator');

    Route::get('/deductions', [DeductionsController::class, 'index'])->middleware('role:HR Administrator');
    Route::post('/deductions', [DeductionsController::class, 'store'])->middleware('role:HR Administrator');
    Route::get('/deductions/{id}', [DeductionsController::class, 'show'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::match(['put', 'patch'], '/deductions/{id}', [DeductionsController::class, 'update'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::delete('/deductions/{id}', [DeductionsController::class, 'destroy'])->whereNumber('id')->middleware('role:HR Administrator');

    Route::get('/performance', [PerformanceEvaluationController::class, 'index'])->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor,Employee');
    Route::post('/performance', [PerformanceEvaluationController::class, 'store'])->middleware('role:HR Administrator,Branch Manager,HR Officer');

    Route::get('/training', [TrainingController::class, 'index'])->middleware('role:HR Administrator,HR Officer,Branch Manager,Auditor');
    Route::post('/training/{employeeId}/enrol', [TrainingController::class, 'enrol'])->whereNumber('employeeId')->middleware('role:HR Administrator,HR Officer');
    Route::get('/training/outstanding', [TrainingController::class, 'outstanding'])->middleware('role:HR Administrator,HR Officer,Branch Manager');

    Route::post('/recruitment', [RecruitmentController::class, 'store'])->middleware('role:HR Administrator');
    Route::post('/applicants/{applicantId}/convert', [RecruitmentController::class, 'convertApplicant'])->whereNumber('applicantId')->middleware('role:HR Administrator');

    Route::get('/deployments', [DeploymentController::class, 'index'])->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');
    Route::post('/deployments', [DeploymentController::class, 'store'])->middleware('role:HR Administrator,Branch Manager,HR Officer');
    Route::get('/branches/{branchId}/deployments/current', [DeploymentController::class, 'currentlyDeployed'])->whereNumber('branchId')->middleware('role:HR Administrator,Branch Manager,HR Officer,Auditor');

    Route::get('/user-accounts', [UserAccountController::class, 'index'])->middleware('role:HR Administrator');
    Route::post('/user-accounts', [UserAccountController::class, 'store'])->middleware('role:HR Administrator');
    Route::get('/user-accounts/{id}', [UserAccountController::class, 'show'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::match(['put', 'patch'], '/user-accounts/{id}', [UserAccountController::class, 'update'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::delete('/user-accounts/{id}', [UserAccountController::class, 'destroy'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::post('/user-accounts/{id}/reset-password', [UserAccountController::class, 'resetPassword'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::patch('/user-accounts/{id}/activate', [UserAccountController::class, 'activate'])->whereNumber('id')->middleware('role:HR Administrator');
    Route::patch('/user-accounts/{id}/deactivate', [UserAccountController::class, 'deactivate'])->whereNumber('id')->middleware('role:HR Administrator');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');

    Route::middleware('role:HR Administrator')->group(function () {
        foreach (array_keys(HrmsEntityRegistry::all()) as $entity) {
            if (in_array($entity, ['allowances', 'deductions', 'employees', 'user-accounts'], true)) {
                continue;
            }

            $uri = $entity;

            Route::get($uri, [EntityController::class, 'index'])->defaults('entity', $entity);
            Route::post($uri, [EntityController::class, 'store'])->defaults('entity', $entity);
            Route::get($uri.'/{id}', [EntityController::class, 'show'])->whereNumber('id')->defaults('entity', $entity);
            Route::match(['put', 'patch'], $uri.'/{id}', [EntityController::class, 'update'])->whereNumber('id')->defaults('entity', $entity);
            Route::delete($uri.'/{id}', [EntityController::class, 'destroy'])->whereNumber('id')->defaults('entity', $entity);
        }
    });
});
