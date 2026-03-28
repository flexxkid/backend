<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalDocuments extends Model
{
    protected $primaryKey = 'DocumentID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'DocumentTypeID',
        'Document',
        'Description',
        'ExpiryDate',
        'UploadDate',
        'UploadedBy',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'DocumentTypeID', 'DocumentTypeID');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(Employee::class, 'UploadedBy', 'EmployeeID');
    }
}
