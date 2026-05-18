<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Payroll extends ErdModel
{
    protected $primaryKey = 'PayrollID';
    public $timestamps = false;
    protected $appends = ['GrossPay', 'TotalAllowances', 'TotalDeductions', 'NetPay', 'Status'];

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

    public function getTotalAllowancesAttribute(): float
    {
        return round((float) $this->allowances->sum('Amount'), 2);
    }

    public function getGrossPayAttribute(): float
    {
        return round((float) $this->BasicSalary + $this->TotalAllowances, 2);
    }

    public function getTotalDeductionsAttribute(): float
    {
        return round((float) $this->deductions->sum(function (AssignedDeduction $deduction) {
            $rate = (float) ($deduction->deduction?->Rate ?? 0);

            if ($rate > 0 && $rate < 1 && (float) $deduction->Amount <= 0) {
                return round($this->GrossPay * $rate, 2);
            }

            return (float) $deduction->Amount;
        }), 2);
    }

    public function getNetPayAttribute(): float
    {
        return round((float) $this->NetSalary, 2);
    }

    public function getStatusAttribute(): string
    {
        return 'Processed';
    }
}
