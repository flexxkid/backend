<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $primaryKey = 'LeaveBalanceID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'LeaveType',
        'TotalDays',
        'UsedDays',
    ];

    // RemainingDays is a GENERATED column — never set it directly
    protected $guarded = ['RemainingDays'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }
}
