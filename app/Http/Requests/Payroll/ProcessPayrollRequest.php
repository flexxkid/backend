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
        ];
    }
}
