<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use App\Models\Project;
use App\Models\Tasks;
use App\Models\User;
use Carbon\Carbon;

class Timesheet extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'timesheets';

    protected $fillable = [
        'project_id',
        'task_id',
        'date',
        'time_log',
        'work_description',
        'employee_id',
        'status'
    ];
    protected $appends = ['hours'];
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
    public function getHoursAttribute()
    {
        if (!$this->start_time || !$this->end_time) {
            return 0; // Return 0 if either time is missing
        }

        $start = Carbon::createFromFormat('H:i', $this->start_time);
        $end = Carbon::createFromFormat('H:i', $this->end_time);

        // Get total hours and minutes separately
        $hours = $end->diff($start)->h;  // Get hour difference
        $minutes = $end->diff($start)->i; // Get minute difference

        // Convert to HH.MM format
        return floatval($hours . '.' . str_pad($minutes, 2, '0', STR_PAD_LEFT));
    }

}
