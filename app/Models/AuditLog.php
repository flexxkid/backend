<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $primaryKey = 'AuditID';
    public $timestamps = false;

    // AuditLog is append-only — no updates or deletes allowed
    protected $fillable = [
        'UserID',
        'Username',
        'Action',
        'AffectedTable',
        'AffectedRecordID',
        'OldValues',
        'NewValues',
        'IPAddress',
        'CreatedAt',
    ];

    protected $casts = [
        'OldValues' => 'array',
        'NewValues' => 'array',
    ];

    // No relationships — records must survive user/record deletion
}
