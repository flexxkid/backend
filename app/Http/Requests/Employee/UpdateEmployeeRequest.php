<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = (int) $this->route('id');

        return [
            'FullName' => ['sometimes', 'required', 'string', 'max:200'],
            'DateOfBirth' => ['sometimes', 'nullable', 'date'],
            'Email' => ['sometimes', 'nullable', 'email', 'max:150', "unique:Employee,Email,{$employeeId},EmployeeID"],
            'PostalAddress' => ['sometimes', 'nullable', 'string', 'max:255'],
            'PhoneNumber' => ['sometimes', 'nullable', 'string', 'max:20'],
            'Gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'JobTitle' => ['sometimes', 'nullable', 'string', 'max:150'],
            'LetterOfApplication' => ['sometimes', 'nullable', 'string', 'max:500'],
            'HighestLevelCertificate' => ['sometimes', 'nullable', 'string', 'max:255'],
            'CV' => ['sometimes', 'nullable', 'string', 'max:500'],
            'ApplicationStatus' => ['sometimes', 'nullable', 'string', 'max:50'],
            'GoodConduct' => ['sometimes', 'nullable', 'string', 'max:500'],
            'NationalID' => ['sometimes', 'required', 'string', 'max:50', "unique:Employee,NationalID,{$employeeId},EmployeeID"],
            'HireDate' => ['sometimes', 'required', 'date'],
            'EmploymentStatus' => ['sometimes', 'required', 'in:Active,Inactive,Suspended'],
            'DepartmentID' => ['sometimes', 'required', 'exists:Department,DepartmentID'],
            'SupervisorID' => ['sometimes', 'nullable', 'exists:Employee,EmployeeID'],
            'BranchID' => ['sometimes', 'required', 'exists:Branch,BranchID'],
        ];
    }
}
