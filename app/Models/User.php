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
        'office_location',
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
        'work_location_id',
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
        'office_location',
        'created_by',
        //Skills
        'skills',

        //Bank details
        'account_holde_name',
        'bank_name',
        'account_number',
        'bank_ifsc_code',
        'bank_branch_location',

        //document type file
        'document_type_id',
        'document',
    ];


    protected $hidden = ['password', 'remember_token'];

    // Relationship to Role
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // Appending custom attribute for role with permissions
    protected $appends = ['role_with_permissions','skills_data'];

    public function getRoleWithPermissionsAttribute()
    {
        if (!$this->role) {
            return null;
        }

        $permissions = Permission::whereIn('_id', $this->role->permissions ?? [])->get(['_id', 'name']);

        return [
            'role_name' => $this->role->name,
            'permissions' => $permissions->map(fn($permission) => [
                'id' => (string) $permission->_id,
                'name' => $permission->name,
            ]),
        ];
    }

    public function getSkillsDataAttribute()
    {

        return Skill::whereIn('_id', $this->skills ?? [])->get();
    }


    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }
    public function employeeType()
    {
        return $this->belongsTo(EmployeeType::class, 'employment_type_id');
    }
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }
    public function employeeStatus()
    {
        return $this->belongsTo(EmployeeStatus::class, 'employee_status_id');
    }
    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }
    public function qualification()
    {
        return $this->belongsTo(Qualification::class, 'qualification_level_id');
    }
    public function reportingManager()
    {
        return $this->belongsTo(User::class, 'reporting_manager_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function tokens()
    {
        return $this->hasMany(PersonalAccessToken::class, 'tokenable_id')->where('tokenable_type', static::class);
    }
}
