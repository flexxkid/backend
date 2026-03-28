<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deductions extends Model
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
