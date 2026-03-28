<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
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
