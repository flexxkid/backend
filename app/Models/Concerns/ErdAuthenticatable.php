<?php

namespace App\Models\Concerns;

use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class ErdAuthenticatable extends Authenticatable
{
    public $timestamps = false;

    public function getTable()
    {
        return $this->table ?? class_basename($this);
    }
}
