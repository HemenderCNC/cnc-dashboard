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
        'task_type',
        'employee_id',
        'dates',  // Array of dates with time logs
        'work_description',
        'status'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', '_id');
    }

    public function task()
    {
        return $this->belongsTo(Tasks::class, 'task_id', '_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', '_id');
    }

    /**
     * Calculate total hours worked for all dates
     */
    public function getTotalHoursAttribute()
    {
        $totalMinutes = 0;

        if (!empty($this->dates)) {
            foreach ($this->dates as $dateEntry) {
                if (!empty($dateEntry['time_log'])) {
                    foreach ($dateEntry['time_log'] as $log) {
                        if (!empty($log['start_time']) && !empty($log['end_time'])) {
                            $start = Carbon::createFromFormat('H:i', $log['start_time']);
                            $end = Carbon::createFromFormat('H:i', $log['end_time']);
                            $totalMinutes += $end->diffInMinutes($start);
                        }
                    }
                }
            }
        }

        return round($totalMinutes / 60, 2); // Convert to hours
    }

    /**
     * Get total hours worked on a specific date
     */
    public function getHoursForDate($date)
    {
        $totalMinutes = 0;

        foreach ($this->dates as $dateEntry) {
            if ($dateEntry['date'] === $date && !empty($dateEntry['time_log'])) {
                foreach ($dateEntry['time_log'] as $log) {
                    if (!empty($log['start_time']) && !empty($log['end_time'])) {
                        $start = Carbon::createFromFormat('H:i', $log['start_time']);
                        $end = Carbon::createFromFormat('H:i', $log['end_time']);
                        $totalMinutes += $end->diffInMinutes($start);
                    }
                }
            }
        }

        return round($totalMinutes / 60, 2); // Convert to hours
    }

    /**
     * Scope: Filter by employee and date range
     */
    public function scopeByEmployee($query, $employeeId, $startDate = null, $endDate = null)
    {
        $query->where('employee_id', $employeeId);
        if ($startDate && $endDate) {
            $query->where('dates.date', '>=', $startDate)
                  ->where('dates.date', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope: Filter by project and date range
     */
    public function scopeByProject($query, $projectId, $startDate = null, $endDate = null)
    {
        $query->where('project_id', $projectId);
        if ($startDate && $endDate) {
            $query->where('dates.date', '>=', $startDate)
                  ->where('dates.date', '<=', $endDate);
        }
        return $query;
    }
    public function scopeLatestForEmployee($query)
    {
        return $query->orderBy('created_at', 'desc')->limit(1);
    }
}
