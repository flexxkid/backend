<?php

namespace App\Services;

use App\Models\AssignedAllowance;
use App\Models\AssignedDeduction;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use App\Models\PayrollDeduction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function process(int $employeeId, string $payPeriod, float $basicSalary, ?string $paymentDate = null): Payroll
    {
        return DB::transaction(function () use ($employeeId, $payPeriod, $basicSalary, $paymentDate) {
            $allowances = AssignedAllowance::query()
                ->where('EmployeeID', $employeeId)
                ->where(function ($query) use ($paymentDate) {
                    $effective = $paymentDate ?? Carbon::now()->toDateString();
                    $query->whereNull('EndDate')->orWhere('EndDate', '>=', $effective);
                })
                ->get();

            $deductions = AssignedDeduction::query()
                ->where('EmployeeID', $employeeId)
                ->where(function ($query) use ($paymentDate) {
                    $effective = $paymentDate ?? Carbon::now()->toDateString();
                    $query->whereNull('EndDate')->orWhere('EndDate', '>=', $effective);
                })
                ->get();

            $totalAllowances = (float) $allowances->sum('Amount');
            $totalDeductions = (float) $deductions->sum('Amount');

            $payroll = Payroll::create([
                'EmployeeID' => $employeeId,
                'PayPeriod' => $payPeriod,
                'BasicSalary' => $basicSalary,
                'NetSalary' => $basicSalary + $totalAllowances - $totalDeductions,
                'PaymentDate' => $paymentDate ?? Carbon::now()->toDateString(),
            ]);

            foreach ($allowances as $allowance) {
                PayrollAllowance::create([
                    'PayrollID' => $payroll->PayrollID,
                    'AssignedAllowanceID' => $allowance->AssignedAllowanceID,
                ]);
            }

            foreach ($deductions as $deduction) {
                PayrollDeduction::create([
                    'PayrollID' => $payroll->PayrollID,
                    'AssignedDeductionID' => $deduction->AssignedDeductionID,
                ]);
            }

            return $payroll->load(['employee', 'allowances.allowance', 'deductions.deduction']);
        });
    }
}
