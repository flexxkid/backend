<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Training extends ErdModel
{
    protected $primaryKey = 'TrainingID';
    public $timestamps = false;

    protected $fillable = [
        'TrainingName',
        'TrainingType',
        'StartDate',
        'EndDate',
    ];

    public function employees()
    {
        return $this->belongsToMany(
            Employee::class,
            'EmployeeTraining',
            'TrainingID',
            'EmployeeID'
        )->withPivot('EmployeeTrainingID', 'CompletionStatus');
    }
}
