<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payroll\ProcessPayrollRequest;
use App\Models\AssignedAllowance;
use App\Models\AssignedDeduction;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Payroll::with(['employee', 'allowances.allowance', 'deductions.deduction'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('PayPeriod'), fn ($query) => $query->where('PayPeriod', $request->string('PayPeriod')))
            ->orderByDesc('PayPeriod')
            ->orderByDesc('PaymentDate');

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(ProcessPayrollRequest $request): JsonResponse
    {
        $payroll = $this->payrollService->process(
            $request->integer('EmployeeID'),
            $request->string('PayPeriod')->toString(),
            (float) $request->input('BasicSalary'),
            $request->input('PaymentDate'),
            $request->input('allowances', []),
            $request->input('deductions', []),
        );

        return response()->json($payroll, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Payroll::with(['employee', 'allowances.allowance', 'deductions.deduction'])->findOrFail($id)
        );
    }

    public function report(Request $request): Response
    {
        $runs = Payroll::with(['employee', 'allowances.allowance', 'deductions.deduction'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('PayPeriod'), fn ($query) => $query->where('PayPeriod', $request->string('PayPeriod')))
            ->orderByDesc('PayPeriod')
            ->get();

        $rows = $runs->map(function (Payroll $run) {
            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%0.2f</td><td>%0.2f</td><td>%0.2f</td><td>%0.2f</td></tr>',
                e($run->employee?->FullName ?? 'N/A'),
                e($run->employee?->JobTitle ?? 'N/A'),
                e((string) $run->PayPeriod),
                (float) $run->BasicSalary,
                (float) $run->GrossPay,
                (float) $run->TotalDeductions,
                (float) $run->NetPay,
            );
        })->implode('');

        return response($this->printableHtml('Payroll Report', [
            'Employee',
            'Job Title',
            'Pay Period',
            'Basic Salary',
            'Gross Pay',
            'Deductions',
            'Net Pay',
        ], $rows), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function assignedAllowances(Request $request): JsonResponse
    {
        $query = AssignedAllowance::with(['employee', 'allowance'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->orderByDesc('EffectiveDate')
            ->orderByDesc('AssignedAllowanceID');

        return response()->json($query->paginate($request->integer('per_page', 100)));
    }

    public function assignedDeductions(Request $request): JsonResponse
    {
        $query = AssignedDeduction::with(['employee', 'deduction'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->orderByDesc('EffectiveDate')
            ->orderByDesc('AssignedDeductionID');

        return response()->json($query->paginate($request->integer('per_page', 100)));
    }

    private function printableHtml(string $title, array $columns, string $rows): string
    {
        $thead = collect($columns)->map(fn (string $column) => '<th>'.e($column).'</th>')->implode('');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{$title}</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 24px; color: #0A1628; }
    h1 { margin-bottom: 8px; }
    p { color: #445; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cfd8e3; padding: 8px; text-align: left; font-size: 12px; }
    th { background: #f3f6fa; }
  </style>
</head>
<body>
  <h1>{$title}</h1>
  <p>Generated at: {$this->escape(now()->toDateTimeString())}</p>
  <table>
    <thead><tr>{$thead}</tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return e($value);
    }
}
