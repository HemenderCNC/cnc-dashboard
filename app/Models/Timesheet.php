<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use App\Models\Project;
use App\Models\Tasks;
use App\Models\User;

class Timesheet extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'timesheets';

    protected $fillable = [
        'project_id',
        'task_id',
        'date',
        'hours',
        'minutes',
        'work_description',
        'employee_id',
        'updated_by'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Tasks::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id', '_id');
    }
}
