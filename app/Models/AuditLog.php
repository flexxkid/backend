<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class AuditLog extends ErdModel
{
    protected $primaryKey = 'AuditID';
    public $timestamps = false;
    protected $appends = ['TableName', 'RecordID', 'Timestamp', 'user'];

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

    public function getTableNameAttribute(): ?string
    {
        return $this->getAttribute('AffectedTable');
    }

    public function getRecordIDAttribute(): ?int
    {
        return $this->getAttribute('AffectedRecordID');
    }

    public function getTimestampAttribute(): ?string
    {
        return $this->getAttribute('CreatedAt');
    }

    public function getUserNameAttribute(): ?string
    {
        return $this->attributes['Username'] ?? null;
    }

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class, 'UserID', 'UserID');
    }

    public function getUserAttribute(): array
    {
        $account = $this->relationLoaded('userAccount') ? $this->userAccount : $this->userAccount()->with('employee')->first();
        $employee = $account?->employee;
        $username = $this->attributes['Username'] ?? null;

        return [
            'UserID' => $this->getAttribute('UserID'),
            'Username' => $username,
            'FullName' => $employee?->FullName ?? $username,
            'EmployeeID' => $employee?->EmployeeID,
        ];
    }
}
