<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
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
