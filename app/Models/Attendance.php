<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $primaryKey = 'AttendanceID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'AttendanceDate',
        'TimeIn',
        'TimeOut',
        'AttendanceStatus',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }
}
