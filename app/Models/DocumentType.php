<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class DocumentType extends ErdModel
{
    protected $primaryKey = 'DocumentTypeID';
    public $timestamps = false;

    protected $fillable = [
        'TypeName',
        'TypeDescription',
    ];

    public function documents()
    {
        return $this->hasMany(AdditionalDocuments::class, 'DocumentTypeID', 'DocumentTypeID');
    }
}
