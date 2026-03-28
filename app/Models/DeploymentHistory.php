<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeploymentHistory extends Model
{
    protected $primaryKey = 'DeploymentID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'BranchID',
        'DeploymentSite',
        'StartDate',
        'EndDate',
        'Reason',
        'DeployedBy',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchID', 'BranchID');
    }

    public function deployedBy()
    {
        return $this->belongsTo(Employee::class, 'DeployedBy', 'EmployeeID');
    }
}
