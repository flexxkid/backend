<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
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
