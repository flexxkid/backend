<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Deductions extends ErdModel
{
    protected $primaryKey = 'DeductionID';
    public $timestamps = false;

    protected $fillable = [
        'DeductionName',
        'Rate',
    ];

    public function assignedDeductions()
    {
        return $this->hasMany(AssignedDeduction::class, 'DeductionID', 'DeductionID');
    }
}
