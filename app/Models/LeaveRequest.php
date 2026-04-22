<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class LeaveRequest extends ErdModel
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
        'ApprovedAt',
    ];

    protected $casts = [
        'ApprovedAt' => 'datetime',
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
