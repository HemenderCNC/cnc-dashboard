<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class PermissionModule extends Eloquent
{
    protected $collection = 'permission_modules';
    protected $fillable = ['name', 'slug'];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'module_id');
    }
}
