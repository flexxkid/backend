<?php

namespace App\Models;

use App\Models\Concerns\ErdModel;

class RolePermission extends ErdModel
{
    protected $primaryKey = 'RolePermissionID';
    public $timestamps = false;

    protected $fillable = [
        'RoleID',
        'PermissionID',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'RoleID', 'RoleID');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'PermissionID', 'PermissionID');
    }
}
