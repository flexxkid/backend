<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recruitment extends Model
{
    protected $primaryKey = 'RecruitmentID';
    public $timestamps = false;

    protected $fillable = [
        'JobTitle',
        'DepartmentID',
        'VacancyStatus',
        'PostedDate',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'DepartmentID', 'DepartmentID');
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class, 'RecruitmentID', 'RecruitmentID');
    }
}
