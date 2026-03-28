<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $primaryKey = 'BranchID';
    public $timestamps = false;

    protected $fillable = [
        'BranchName',
        'BranchLocation',
        'BranchPhone',
        'BranchEmail',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'BranchID', 'BranchID');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'BranchID', 'BranchID');
    }

    public function deploymentHistories()
    {
        return $this->hasMany(DeploymentHistory::class, 'BranchID', 'BranchID');
    }
}
