<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Tasks extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'tasks';
    protected $fillable = ['title','project_id','milestone_id','status_id','task_type_id','priority','owner_id','assignee_id','description','due_date','estimated_hours','attachment','created_by','start_date'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
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
}
