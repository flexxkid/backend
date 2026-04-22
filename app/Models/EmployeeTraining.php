<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class EmployeeTraining extends ErdModel
{
    protected $primaryKey = 'EmployeeTrainingID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'TrainingID',
        'CompletionStatus',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function training()
    {
        return $this->belongsTo(Training::class, 'TrainingID', 'TrainingID');
    }
}
