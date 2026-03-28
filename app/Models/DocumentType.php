<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
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
