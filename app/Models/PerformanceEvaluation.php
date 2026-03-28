<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceEvaluation extends Model
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
