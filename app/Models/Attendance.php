<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Attendance extends ErdModel
{
    protected $primaryKey = 'AttendanceID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'AttendanceDate',
        'Time_In',
        'Time_Out',
        'AttendanceStatus',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }
}
