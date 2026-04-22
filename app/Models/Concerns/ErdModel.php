<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

abstract class ErdModel extends Model
{
    public $timestamps = false;

    public function getTable()
    {
        return $this->table ?? class_basename($this);
    }
}
