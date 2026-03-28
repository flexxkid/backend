<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $primaryKey = 'EmployeeID';
    public $timestamps = false;

    protected $fillable = [
        'FullName',
        'DateOfBirth',
        'Email',
        'Address',
        'PhoneNumber',
        'Gender',
        'JobTitle',
        'LetterOfApplication',
        'HighestLevelCertificate',
        'CV',
        'ApplicationStatus',
        'GoodConduct',
        'NationalID',
        'HireDate',
        'EmploymentStatus',
        'DepartmentID',
        'SupervisorID',
        'BranchID',
    ];

    protected $hidden = ['CV', 'GoodConduct'];

    // ── Belongs To ──────────────────────────────────────────

    public function department()
    {
        return $this->belongsTo(Department::class, 'DepartmentID', 'DepartmentID');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchID', 'BranchID');
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'SupervisorID', 'EmployeeID');
    }

    // ── Has One ─────────────────────────────────────────────

    public function userAccount()
    {
        return $this->hasOne(UserAccount::class, 'EmployeeID', 'EmployeeID');
    }

    // ── Has Many ────────────────────────────────────────────

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'SupervisorID', 'EmployeeID');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'EmployeeID', 'EmployeeID');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'EmployeeID', 'EmployeeID');
    }

    public function approvedLeaves()
    {
        return $this->hasMany(LeaveRequest::class, 'ApprovedBy', 'EmployeeID');
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class, 'EmployeeID', 'EmployeeID');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'EmployeeID', 'EmployeeID');
    }

    public function performanceEvaluations()
    {
        return $this->hasMany(PerformanceEvaluation::class, 'EmployeeID', 'EmployeeID');
    }

    public function evaluationsGiven()
    {
        return $this->hasMany(PerformanceEvaluation::class, 'EvaluatorID', 'EmployeeID');
    }

    public function assignedAllowances()
    {
        return $this->hasMany(AssignedAllowance::class, 'EmployeeID', 'EmployeeID');
    }

    public function assignedDeductions()
    {
        return $this->hasMany(AssignedDeduction::class, 'EmployeeID', 'EmployeeID');
    }

    public function documents()
    {
        return $this->hasMany(AdditionalDocuments::class, 'EmployeeID', 'EmployeeID');
    }

    public function uploadedDocuments()
    {
        return $this->hasMany(AdditionalDocuments::class, 'UploadedBy', 'EmployeeID');
    }

    public function deploymentHistories()
    {
        return $this->hasMany(DeploymentHistory::class, 'EmployeeID', 'EmployeeID');
    }

    public function deploymentsAuthorised()
    {
        return $this->hasMany(DeploymentHistory::class, 'DeployedBy', 'EmployeeID');
    }

    // ── Many to Many ────────────────────────────────────────

    public function trainings()
    {
        return $this->belongsToMany(
            Training::class,
            'EmployeeTraining',
            'EmployeeID',
            'TrainingID'
        )->withPivot('EmployeeTrainingID', 'CompletionStatus');
    }
}
