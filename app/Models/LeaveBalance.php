<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class LeaveBalance extends ErdModel
{
    protected $primaryKey = 'LeaveBalanceID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'LeaveType',
        'TotalDays',
        'UsedDays',
        'RemainingDays',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }
}
