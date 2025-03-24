<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Permission extends Eloquent
{
    protected $connection = 'mongodb';  // Specify MongoDB connection
    protected $fillable = ['name','slug', 'module_id'];  // Define the fields for permissions

    // Relationship to roles
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }

    // Relationship to permission module (Many-to-One)
    public function module()
    {
        return $this->belongsTo(PermissionModule::class, 'module_id', '_id');
    }
}