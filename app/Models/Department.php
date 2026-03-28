<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $primaryKey = 'DepartmentID';
    public $timestamps = false;

    protected $fillable = [
        'DepartmentName',
        'HODID',
        'BranchID',
        'DepartmentDescription',
        'CreatedDate',
    ];

    public function headOfDepartment()
    {
        return $this->belongsTo(Employee::class, 'HODID', 'EmployeeID');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchID', 'BranchID');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'DepartmentID', 'DepartmentID');
    }

    public function recruitments()
    {
        return $this->hasMany(Recruitment::class, 'DepartmentID', 'DepartmentID');
    }
}
