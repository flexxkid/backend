<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payroll\ProcessPayrollRequest;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Payroll::with(['employee', 'allowances.allowance', 'deductions.deduction'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('PayPeriod'), fn ($query) => $query->where('PayPeriod', $request->string('PayPeriod')));

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(ProcessPayrollRequest $request): JsonResponse
    {
        $payroll = $this->payrollService->process(
            $request->integer('EmployeeID'),
            $request->string('PayPeriod')->toString(),
            (float) $request->input('BasicSalary'),
            $request->input('PaymentDate'),
        );

        return response()->json($payroll, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Payroll::with(['employee', 'allowances.allowance', 'deductions.deduction'])->findOrFail($id)
        );
    }
}
