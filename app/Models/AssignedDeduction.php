<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignedDeduction extends Model
{
    protected $primaryKey = 'AssignedDeductionID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'DeductionID',
        'EffectiveDate',
        'EndDate',
        'Amount',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function deduction()
    {
        return $this->belongsTo(Deductions::class, 'DeductionID', 'DeductionID');
    }

    public function payrolls()
    {
        return $this->belongsToMany(
            Payroll::class,
            'PayrollDeduction',
            'AssignedDeductionID',
            'PayrollID'
        )->withPivot('PayrollDeductionID');
    }
}
