<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'FullName' => ['nullable', 'string', 'max:200'],
            'FirstName' => ['required_without:FullName', 'string', 'max:100'],
            'LastName' => ['required_without:FullName', 'string', 'max:100'],
            'DateOfBirth' => ['nullable', 'date'],
            'Email' => ['nullable', 'email', 'max:150', 'unique:Employee,Email'],
            'PostalAddress' => ['nullable', 'string', 'max:255'],
            'PhoneNumber' => ['nullable', 'string', 'max:20'],
            'Gender' => ['nullable', 'string', 'max:20'],
            'JobTitle' => ['nullable', 'string', 'max:150'],
            'LetterOfApplication' => ['nullable', 'string', 'max:500'],
            'HighestLevelCertificate' => ['nullable', 'string', 'max:255'],
            'CV' => ['nullable', 'string', 'max:500'],
            'ApplicationStatus' => ['nullable', 'string', 'max:50'],
            'GoodConduct' => ['nullable', 'string', 'max:500'],
            'NationalID' => ['required', 'string', 'max:50', 'unique:Employee,NationalID'],
            'HireDate' => ['required', 'date'],
            'EmploymentStatus' => ['required', 'in:Active,Inactive,Suspended'],
            'DepartmentID' => ['required', 'exists:Department,DepartmentID'],
            'SupervisorID' => ['nullable', 'exists:Employee,EmployeeID'],
            'BranchID' => ['required', 'exists:Branch,BranchID'],
        ];
    }
}
