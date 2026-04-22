<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Allowances extends ErdModel
{
    protected $primaryKey = 'AllowanceID';
    public $timestamps = false;

    protected $fillable = [
        'AllowanceName',
    ];

    public function assignedAllowances()
    {
        return $this->hasMany(AssignedAllowance::class, 'AllowanceID', 'AllowanceID');
    }
}
