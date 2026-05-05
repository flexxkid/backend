<?php

use App\Http\Controllers\RecruitmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/applicants/{applicantId}/documents/{field}', [RecruitmentController::class, 'showApplicantDocument'])
    ->whereNumber('applicantId')
    ->middleware('signed')
    ->name('applicants.documents.show');
