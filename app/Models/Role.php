<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
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
