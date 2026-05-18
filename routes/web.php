<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RecruitmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/applicants/{applicantId}/documents/{field}', [RecruitmentController::class, 'showApplicantDocument'])
    ->whereNumber('applicantId')
    ->middleware('signed')
    ->name('applicants.documents.show');

Route::get('/documents/{documentId}/open', [DocumentController::class, 'stream'])
    ->whereNumber('documentId')
    ->middleware('signed')
    ->name('employees.documents.show');

Route::get('/employees/{employeeId}/documents/{field}', [EmployeeController::class, 'streamApplicationDocument'])
    ->whereNumber('employeeId')
    ->middleware('signed')
    ->name('employees.application-documents.show');
