<?php
// app/Models/Role.php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use App\Models\Permission;  // Import the Permission model

class Role extends Eloquent
{
    protected $connection = 'mongodb';  // Specify MongoDB connection
    protected $fillable = ['name', 'permissions', 'slug'];  // Define the fields you need

    // Define the relationship to permissions (many-to-many)
    public function get_permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }
    public function getPermissionObjectsAttribute()
    {
        // Ensure $this->permissions is an array of IDs
        $permissionIds = $this->permissions ?? [];
        return Permission::whereIn('_id', $permissionIds)
                    ->with('module')  // eager load module if needed
                    ->get();
    }
    // Relationship to users
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
    }
}
