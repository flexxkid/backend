<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Permission extends ErdModel
{
    protected $primaryKey = 'PermissionID';
    public $timestamps = false;
    protected $appends = ['PermissionDescription'];

    protected $fillable = [
        'PermissionName',
        'Description',
    ];

    public function getPermissionDescriptionAttribute(): ?string
    {
        return $this->Description;
    }

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'RolePermission',
            'PermissionID',
            'RoleID'
        )->withPivot('RolePermissionID');
    }
}
