<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class UserAccount extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'UserAccount';
    protected $primaryKey = 'UserID';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'Username',
        'PasswordHash',
        'RoleID',
        'AccountStatus',
        'LastLogin',
    ];

    protected $hidden = ['PasswordHash'];

    // Tell Laravel which column holds the password
    public function getAuthPassword()
    {
        return $this->PasswordHash;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeID', 'EmployeeID');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'RoleID', 'RoleID');
    }

    public function notifications()
    {
        return $this->hasMany(Notifications::class, 'RecipientUserID', 'UserID');
    }
}
