<?php

namespace App\Services;

use App\Models\AssignedAllowance;
use App\Models\AssignedDeduction;
use App\Models\Allowances;
use App\Models\Deductions;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use App\Models\PayrollDeduction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function process(
        int $employeeId,
        string $payPeriod,
        float $basicSalary,
        ?string $paymentDate = null,
        array $selectedAllowances = [],
        array $selectedDeductions = [],
    ): Payroll
    {
        return DB::transaction(function () use ($employeeId, $payPeriod, $basicSalary, $paymentDate, $selectedAllowances, $selectedDeductions) {
            $effectiveDate = $paymentDate ?? Carbon::now()->toDateString();

            if ($selectedAllowances !== []) {
                $this->syncSelectedAllowances($employeeId, $effectiveDate, collect($selectedAllowances));
            }

            $allowances = $this->activeAllowances($employeeId, $effectiveDate);
            $totalAllowances = (float) $allowances->sum('Amount');
            $grossPay = $basicSalary + $totalAllowances;

            if ($selectedDeductions !== []) {
                $this->syncSelectedDeductions($employeeId, $effectiveDate, $grossPay, collect($selectedDeductions));
            }

            $deductions = $this->activeDeductions($employeeId, $effectiveDate);
            $totalDeductions = (float) $deductions->sum(function (AssignedDeduction $deduction) use ($grossPay) {
                $rate = (float) ($deduction->deduction?->Rate ?? 0);

                if ($rate > 0 && $rate < 1 && (float) $deduction->Amount <= 0) {
                    return round($grossPay * $rate, 2);
                }

                return (float) $deduction->Amount;
            });

            $netSalary = $grossPay - $totalDeductions;

            $payroll = Payroll::updateOrCreate([
                'EmployeeID' => $employeeId,
                'PayPeriod' => $payPeriod,
            ], [
                'BasicSalary' => $basicSalary,
                'NetSalary' => $netSalary,
                'PaymentDate' => $effectiveDate,
            ]);

            PayrollAllowance::query()->where('PayrollID', $payroll->PayrollID)->delete();
            PayrollDeduction::query()->where('PayrollID', $payroll->PayrollID)->delete();

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

            return $this->decoratePayroll(
                $payroll->fresh()->load(['employee', 'allowances.allowance', 'deductions.deduction'])
            );
        });
    }

    private function activeAllowances(int $employeeId, string $effectiveDate): Collection
    {
        return AssignedAllowance::query()
            ->with('allowance')
            ->where('EmployeeID', $employeeId)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('EffectiveDate')->orWhere('EffectiveDate', '<=', $effectiveDate);
            })
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('EndDate')->orWhere('EndDate', '>=', $effectiveDate);
            })
            ->get();
    }

    private function activeDeductions(int $employeeId, string $effectiveDate): Collection
    {
        return AssignedDeduction::query()
            ->with('deduction')
            ->where('EmployeeID', $employeeId)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('EffectiveDate')->orWhere('EffectiveDate', '<=', $effectiveDate);
            })
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('EndDate')->orWhere('EndDate', '>=', $effectiveDate);
            })
            ->get();
    }

    private function syncSelectedAllowances(int $employeeId, string $effectiveDate, Collection $selectedAllowances): void
    {
        $definitions = Allowances::query()
            ->whereIn('AllowanceID', $selectedAllowances->pluck('id')->all())
            ->get()
            ->keyBy('AllowanceID');

        foreach ($selectedAllowances as $item) {
            if (! $definitions->has($item['id'])) {
                continue;
            }

            $record = AssignedAllowance::query()->firstOrNew([
                'EmployeeID' => $employeeId,
                'AllowanceID' => $item['id'],
                'EndDate' => null,
            ]);

            $record->fill([
                'EffectiveDate' => $record->EffectiveDate ?? $effectiveDate,
                'Amount' => (float) ($item['amount'] ?? 0),
                'IsTaxable' => (bool) ($item['is_taxable'] ?? $record->IsTaxable ?? false),
            ]);
            $record->save();
        }
    }

    private function syncSelectedDeductions(int $employeeId, string $effectiveDate, float $grossPay, Collection $selectedDeductions): void
    {
        $definitions = Deductions::query()
            ->whereIn('DeductionID', $selectedDeductions->pluck('id')->all())
            ->get()
            ->keyBy('DeductionID');

        foreach ($selectedDeductions as $item) {
            /** @var Deductions|null $definition */
            $definition = $definitions->get($item['id']);

            if (! $definition) {
                continue;
            }

            $amount = (float) ($item['amount'] ?? 0);
            $rate = (float) ($definition->Rate ?? 0);

            if ($rate > 0 && $rate < 1 && $amount <= 0) {
                $amount = round($grossPay * $rate, 2);
            }

            $record = AssignedDeduction::query()->firstOrNew([
                'EmployeeID' => $employeeId,
                'DeductionID' => $item['id'],
                'EndDate' => null,
            ]);

            $record->fill([
                'EffectiveDate' => $record->EffectiveDate ?? $effectiveDate,
                'Amount' => $amount,
            ]);
            $record->save();
        }
    }

    private function decoratePayroll(Payroll $payroll): Payroll
    {
        $totalAllowances = (float) $payroll->allowances->sum('Amount');
        $grossPay = (float) $payroll->BasicSalary + $totalAllowances;
        $totalDeductions = (float) $payroll->deductions->sum(function (AssignedDeduction $deduction) use ($grossPay) {
            $rate = (float) ($deduction->deduction?->Rate ?? 0);

            if ($rate > 0 && $rate < 1 && (float) $deduction->Amount <= 0) {
                return round($grossPay * $rate, 2);
            }

            return (float) $deduction->Amount;
        });

        $payroll->setAttribute('TotalAllowances', round($totalAllowances, 2));
        $payroll->setAttribute('GrossPay', round($grossPay, 2));
        $payroll->setAttribute('TotalDeductions', round($totalDeductions, 2));
        $payroll->setAttribute('NetPay', round((float) $payroll->NetSalary, 2));
        $payroll->setAttribute('Status', 'Processed');

        return $payroll;
    }
}
