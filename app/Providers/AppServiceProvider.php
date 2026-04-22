<?php

namespace App\Providers;

use App\Models\AdditionalDocuments;
use App\Models\Applicant;
use App\Models\AssignedAllowance;
use App\Models\AssignedDeduction;
use App\Models\Attendance;
use App\Models\DeploymentHistory;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\LeaveRequest;
use App\Models\Notifications;
use App\Models\Payroll;
use App\Models\PerformanceEvaluation;
use App\Models\Recruitment;
use App\Models\UserAccount;
use App\Observers\AuditableObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            Employee::class,
            AdditionalDocuments::class,
            LeaveRequest::class,
            Attendance::class,
            Payroll::class,
            PerformanceEvaluation::class,
            Recruitment::class,
            Applicant::class,
            DeploymentHistory::class,
            UserAccount::class,
            Notifications::class,
            AssignedAllowance::class,
            AssignedDeduction::class,
            EmployeeTraining::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
