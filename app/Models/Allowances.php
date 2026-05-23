<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Allowances extends ErdModel
{
    protected $primaryKey = 'AllowanceID';
    public $timestamps = false;

    protected $fillable = [
        'AllowanceName',
        'DefaultAmount',
    ];

    protected $casts = [
        'DefaultAmount' => 'float',
    ];

    public function assignedAllowances()
    {
        return $this->hasMany(AssignedAllowance::class, 'AllowanceID', 'AllowanceID');
    }
}
