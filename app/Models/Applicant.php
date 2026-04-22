<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Applicant extends ErdModel
{
    protected $primaryKey = 'ApplicationID';
    public $timestamps = false;

    protected $fillable = [
        'FullName',
        'DateOfBirth',
        'Email',
        'Address',
        'PhoneNumber',
        'Gender',
        'LetterOfApplication',
        'HighestLevelCertificate',
        'CV',
        'ApplicationStatus',
        'GoodConduct',
        'NationalID',
        'RecruitmentID',
    ];

    protected $hidden = ['CV', 'GoodConduct'];

    public function recruitment()
    {
        return $this->belongsTo(Recruitment::class, 'RecruitmentID', 'RecruitmentID');
    }
}
