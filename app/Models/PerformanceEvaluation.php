<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class PerformanceEvaluation extends ErdModel
{
    protected $primaryKey = 'EvaluationID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'EvaluatorID',
        'EvaluationPeriod',
        'Score',
        'Comments',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function evaluator()
    {
        return $this->belongsTo(Employee::class, 'EvaluatorID', 'EmployeeID');
    }
}
