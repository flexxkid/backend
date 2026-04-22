<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class Role extends ErdModel
{
    protected $primaryKey = 'RoleID';
    public $timestamps = false;

    protected $fillable = [
        'RoleName',
        'RoleDescription',
    ];

    public function userAccounts()
    {
        return $this->hasMany(UserAccount::class, 'RoleID', 'RoleID');
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'RolePermission',
            'RoleID',
            'PermissionID'
        )->withPivot('RolePermissionID');
    }
}
