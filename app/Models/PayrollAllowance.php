<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAllowance extends Model
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
