<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Recruitment extends ErdModel
{
    protected $table = 'Recruitment';
    protected $primaryKey = 'RecruitmentID';
    public $timestamps = false;

    protected $fillable = [
        'JobTitle',
        'DepartmentID',
        'location',
        'category',
        'type',
        'salary',
        'description',
        'tags',
        'VacancyStatus',
        'PostedDate',
        'Deadline',
    ];

    protected $casts = [
        'salary' => 'double',
        'PostedDate' => 'date',
        'Deadline' => 'date',
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