<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use Carbon\Carbon;
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
        'in_time',
        'out_time',
        'adharcard_number',
        'pancard_number',
        'employee_id',
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
        'account_type',
        'bank_ifsc_code',
        'bank_branch_location',

        //document type file
        'reporting_manager_id',
        'document_type_id',
        'document',
        'fcm_token'
    ];


    protected $hidden = ['password', 'remember_token'];
    protected $appends = ['role_with_permissions','skills_data', 'working', 'total_work_hours', 'total_projects', 'today_tasks'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            $user->employee_id = self::generateSequentialEmployeeCode();
        });
    }
    private static function generateSequentialEmployeeCode()
    {
        $lastProject = self::where('employee_id', '!=', null)->orderBy('employee_id', 'desc')->first();
        $nextNumber = 1;
        if ($lastProject && preg_match('/EMP-(\d+)/', $lastProject->employee_id, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }
        return 'EMP-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }
    public function getWorkingAttribute()
    {
        $timesheet = Timesheet::where('employee_id', (string) $this->_id) // Ensure it's a string comparison
            ->where('status', 'running')
            ->exists();

        return $timesheet ? true : false;
    }
    public function getTotalWorkHoursAttribute()
    {
        $timesheets = Timesheet::where('employee_id', (string) $this->_id)->get();
        $totalMinutes = 0;

        foreach ($timesheets as $timesheet) {
            // Ensure there is a dates array
            if (!empty($timesheet->dates) && is_array($timesheet->dates)) {
                foreach ($timesheet->dates as $dateEntry) {
                    if (isset($dateEntry['time_log']) && is_array($dateEntry['time_log'])) {
                        foreach ($dateEntry['time_log'] as $log) {
                            // Parse start_time and end_time with the expected format "H:i"
                            $start = Carbon::createFromFormat('H:i', $log['start_time']);
                            $end = Carbon::createFromFormat('H:i', $log['end_time']);
                            $totalMinutes += $end->diffInMinutes($start);
                        }
                    }
                }
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return "{$hours}h {$minutes}m";
    }

    public function getTotalProjectsAttribute()
    {
        $projects = Project::whereIn('assignee', [(string) $this->_id])->count();
        return $projects;
    }
    public function getTodayTasksAttribute()
    {
        $today = Carbon::now()->toDateString();
        $projects = Tasks::where('due_date', '>=', $today)->where('assignee_id',(string) $this->_id)->count();
        return $projects;
    }
    // Relationship to Role
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getRoleWithPermissionsAttribute()
{
    if (!$this->role) {
        return null;
    }

    // Check if the relationship is loaded; if not, load it along with each permission’s module.
    if (!$this->role->relationLoaded('get_permissions')) {
        $this->role->load('get_permissions.module');
    }

    /**Modified code */
    // Load permissions manually
    $permissions = Permission::whereIn('_id', $this->role->permissions ?? [])->with('module')->get();

    return [
        'role_name' => $this->role->name,
        'permissions' => $permissions->map(function ($permission) {
            return [
                'id'              => (string) $permission->_id,
                'name'            => $permission->name,
                'permission_slug' => $permission->slug,
                'module'          => optional($permission->module)->name,
                'module_slug'     => optional($permission->module)->slug,
            ];
        }),
    ];


    /**Milans code */

    // $permissions = $this->role->get_permissions;

    // return [
    //     'role_name' => $this->role->name,
    //     'permissions' => $permissions->map(function ($permission) {
    //         return [
    //             'id'              => (string) $permission->_id,
    //             'name'            => $permission->name,
    //             'permission_slug' => $permission->slug,
    //             'module'          => optional($permission->module)->name,
    //             'module_slug'     => optional($permission->module)->slug,
    //         ];
    //     }),
    // ];
}

    public function getSkillsDataAttribute()
    {

        return Skill::whereIn('_id', $this->skills ?? [])->get();
    }

    public function latestTimesheet()
    {
        return $this->hasOne(Timesheet::class, 'employee_id')->latest();
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
