<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollDeduction extends Model
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
