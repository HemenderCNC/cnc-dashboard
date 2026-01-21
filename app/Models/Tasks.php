<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Tasks extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'tasks';
    protected $fillable = ['task_id', 'title', 'project_id', 'milestone_id', 'status_id', 'task_type_id', 'priority', 'owner_id', 'assignee_id', 'assignees', 'description', 'due_date', 'estimated_hours', 'attachment', 'created_by', 'start_date', 
    'qa_id','parent_task_id','is_child_task'];
    public static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            // Generate the client ID
            $lastTask = self::orderBy('_id', 'desc')->first();
            $lastId = $lastTask && preg_match('/CNC-(\d+)/i', $lastTask->task_id, $matches)
                ? intval($matches[1])
                : 0;
            $task->task_id = 'CNC-' . str_pad($lastId + 1, 2, '0', STR_PAD_LEFT);
        });
    }
    public function getAssigneesDataAttribute()
    {
        return User::whereIn('_id', $this->assignees ?: [])->get(['_id','name','last_name','email','profile_photo','contact_number']);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function qa()
    {
        return $this->belongsTo(User::class, 'qa_id');
    }
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
    public function milestone()
    {
        return $this->belongsTo(Milestones::class, 'milestone_id');
    }
    public function status()
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }
    public function taskType()
    {
        return $this->belongsTo(TaskType::class, 'task_type_id');
    }
    public function parentTask()
{
    return $this->belongsTo(self::class, 'parent_task_id', '_id');
}

public function childTasks()
{
    return $this->hasMany(self::class, 'parent_task_id', '_id');
}
    
}
