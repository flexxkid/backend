<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Allowances extends Model
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
