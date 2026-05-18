<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'EmployeeID' => ['required', 'exists:Employee,EmployeeID'],
            'PayPeriod' => ['required', 'string', 'max:100'],
            'BasicSalary' => ['required', 'numeric', 'min:0'],
            'PaymentDate' => ['nullable', 'date'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.id' => ['required_with:allowances', 'integer', 'exists:Allowances,AllowanceID'],
            'allowances.*.amount' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.id' => ['required_with:deductions', 'integer', 'exists:Deductions,DeductionID'],
            'deductions.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
