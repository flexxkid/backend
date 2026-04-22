<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class PayrollAllowance extends ErdModel
{
    protected $primaryKey = 'PayrollAllowanceID';
    public $timestamps = false;

    protected $fillable = [
        'PayrollID',
        'AssignedAllowanceID',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'PayrollID', 'PayrollID');
    }

    public function assignedAllowance()
    {
        return $this->belongsTo(AssignedAllowance::class, 'AssignedAllowanceID', 'AssignedAllowanceID');
    }
}
