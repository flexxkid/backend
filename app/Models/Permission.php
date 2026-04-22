<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Permission extends ErdModel
{
    protected $primaryKey = 'PermissionID';
    public $timestamps = false;

    protected $fillable = [
        'PermissionName',
        'Description',
    ];

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
