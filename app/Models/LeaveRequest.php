<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $table = 'LeaveRequest';
    protected $primaryKey = 'LeaveID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'LeaveType',
        'StartDate',
        'EndDate',
        'Reason',
        'LeaveStatus',
        'ApprovedBy',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'ApprovedBy', 'EmployeeID');
    }
}
