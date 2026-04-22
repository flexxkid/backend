<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class AssignedAllowance extends ErdModel
{
    protected $primaryKey = 'AssignedAllowanceID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'AllowanceID',
        'EffectiveDate',
        'EndDate',
        'IsTaxable',
        'Amount',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function allowance()
    {
        return $this->belongsTo(Allowances::class, 'AllowanceID', 'AllowanceID');
    }

    public function payrolls()
    {
        return $this->belongsToMany(
            Payroll::class,
            'PayrollAllowance',
            'AssignedAllowanceID',
            'PayrollID'
        )->withPivot('PayrollAllowanceID');
    }
}
