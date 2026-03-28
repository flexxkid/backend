<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $primaryKey = 'PayrollID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'PayPeriod',
        'BasicSalary',
        'NetSalary',
        'PaymentDate',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function allowances()
    {
        return $this->belongsToMany(
            AssignedAllowance::class,
            'PayrollAllowance',
            'PayrollID',
            'AssignedAllowanceID'
        )->withPivot('PayrollAllowanceID');
    }

    public function deductions()
    {
        return $this->belongsToMany(
            AssignedDeduction::class,
            'PayrollDeduction',
            'PayrollID',
            'AssignedDeductionID'
        )->withPivot('PayrollDeductionID');
    }
}
