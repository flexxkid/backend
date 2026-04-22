<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class PayrollDeduction extends ErdModel
{
    protected $primaryKey = 'PayrollDeductionID';
    public $timestamps = false;

    protected $fillable = [
        'PayrollID',
        'AssignedDeductionID',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'PayrollID', 'PayrollID');
    }

    public function assignedDeduction()
    {
        return $this->belongsTo(AssignedDeduction::class, 'AssignedDeductionID', 'AssignedDeductionID');
    }
}
