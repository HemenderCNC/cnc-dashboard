<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Project extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'projects';

    protected $fillable = [
        'project_code',
        'project_name',
        'project_industry',
        'project_type',
        'estimated_hours',
        'project_description',
        'priority',
        'budget',
        'project_status_id',
        'platforms',
        'languages',
        'estimated_start_date',
        'estimated_end_date',
        'actual_start_date',
        'actual_end_date',
        'client_id',
        'assignee',
        'project_manager_id',
        'other_details',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($project) {
            $project->project_code = self::generateSequentialProjectCode();
        });
        static::deleting(function ($project) {
            Milestones::where('project_id', $project->_id)->update(['project_id' => null]);
        });
    }
    private static function generateSequentialProjectCode()
    {
        $lastProject = self::orderBy('project_code', 'desc')->first();
        $nextNumber = $lastProject ? ((int)substr($lastProject->project_code, 3)) + 1 : 1;
        return 'cnc' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }
    public function client()
    {
        return $this->belongsTo(Clients::class, 'client_id');
    }
    public function projectStatus()
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }
    public function getAssigneesDataAttribute()
    {
        return User::whereIn('_id', $this->assignee ?? [])->get();
    }
    public function getLanguagesDataAttribute()
    {
        return Languages::whereIn('_id', $this->languages ?? [])->get();
    }
    public function getPlatformsDataAttribute()
    {
        return Platform::whereIn('_id', $this->platforms ?? [])->get();
    }
}
