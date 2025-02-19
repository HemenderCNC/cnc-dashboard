<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    use Notifiable;
    protected $connection = 'mongodb';
    protected $fillable = [
        // Basic Information
        'name',
        'last_name',
        'gender',
        'contact_number',
        'birthdate',
        'personal_email',
        'blood_group',
        'marital_status',
        'nationality',
        'profile_photo',
        // Address Information
        'residential_address',
        'permanent_address',
        'country',
        'city',
        'postal_code',
        'emergency_contact_number',
        // Qualification
        'qualification_level_id',
        'certification_name',
        'year_of_completion',
        'qualification_document',
        // Work Information
        'email',
        'username',
        'password',
        'role_id',
        'department_id',
        'designation_id',
        'joining_date',
        'in_out_time',
        'adharcard_number',
        'pancard_number',
        'employment_type_id',
        'employee_status_id',
        'created_by',
        //Skills
        'skills'
    ];

    protected $hidden = ['password', 'remember_token'];

    // Relationship to Role
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // Appending custom attribute for role with permissions
    protected $appends = ['role_with_permissions'];

    public function getRoleWithPermissionsAttribute()
    {
        $role = $this->role;

        if ($role) {
            // Retrieve permissions for the role
            $permissionIds =$role->permissions;

            if($permissionIds){
                // Fetch all permissions by IDs and get only their names
                // $permissions = Permission::whereIn('_id', $permissionIds)->pluck('name');
                $permissions = Permission::whereIn('_id', $permissionIds)->get(['_id', 'name']);

                return [
                    'role_name' => $role->name,
                    // 'permissions' => $permissions->toArray()
                    'permissions' => $permissions->map(function ($permission) {
                    return [
                        'id' => (string) $permission->_id,
                        'name' => $permission->name,
                    ];
                }),
                ];
            }

        }

        return null;
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function tokens()
    {
        return $this->hasMany(PersonalAccessToken::class, 'tokenable_id')->where('tokenable_type', self::class);
    }
}
