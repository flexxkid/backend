<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Notifications extends ErdModel
{
    protected $primaryKey = 'NotificationID';
    public $timestamps = false;

    protected $fillable = [
        'RecipientUserID',
        'Title',
        'Message',
        'NotificationType',
        'ReferenceTable',
        'ReferenceID',
        'IsRead',
        'CreatedAt',
        'ReadAt',
    ];

    protected $casts = [
        'IsRead' => 'boolean',
    ];

    public function recipient()
    {
        return $this->belongsTo(UserAccount::class, 'RecipientUserID', 'UserID');
    }
}
