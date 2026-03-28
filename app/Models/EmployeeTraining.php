<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeTraining extends Model
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
