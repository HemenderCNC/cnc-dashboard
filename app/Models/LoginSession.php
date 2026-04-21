<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use Carbon\Carbon;

class LoginSession extends Eloquent
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'login_sessions';

    protected $fillable = [
        'employee_id',
        'date',
        'time_log',
        'break',
        'break_log',
        'is_logout',
        'actual_check_in_time',
        'actual_check_in_date',
        'actual_check_out_time',
        'actual_check_out_date',
    ];

    protected $appends = ['last_updated_at','actual_total_login_time','actual_check_in_time', 'actual_check_in_date', 'actual_check_out_time', 'actual_check_out_date', 'check_in_time', 'check_out_time', 'total_login_time','total_working_time','total_break_time','is_logout'];

    public function getLastUpdatedAtAttribute()
    {
        return $this->attributes['updated_at'] ?? null;
    }
    public function getActualTotalLoginTimeAttribute()
    {
        $totalSeconds = 0;

        if (!empty($this->actual_check_in_time) && !empty($this->actual_check_out_time)) {
            try {
                $start = Carbon::createFromFormat('H:i', $this->actual_check_in_time);
                $end = Carbon::createFromFormat('H:i', $this->actual_check_out_time);

                if ($end->lt($start)) {
                    $end->addDay();
                }

                $totalSeconds = $start->diffInSeconds($end);
            } catch (\Exception $e) {
                // Return 00:00 if time format is invalid
            }
        }

        return gmdate('H:i', $totalSeconds);
    }

    public function getActualCheckInTimeAttribute()
    {
        return $this->attributes['actual_check_in_time'] ?? null;
    }
    public function getActualCheckOutTimeAttribute()
    {
        return $this->attributes['actual_check_out_time'] ?? null;
    }
    public function getActualCheckInDateAttribute()
    {
        return $this->attributes['actual_check_in_date'] ?? null;
    }
    public function getActualCheckOutDateAttribute()
    {
        return $this->attributes['actual_check_out_date'] ?? null;
    }
    public function getCheckInTimeAttribute()
    {
        return !empty($this->time_log) ? $this->time_log[0]['start_time'] : null;
    }
    public function getCheckOutTimeAttribute()
    {
        return !empty($this->time_log) ? $this->time_log[count($this->time_log) - 1]['end_time'] : null;
    }
    public function getTotalLoginTimeAttribute()
{
    $totalSeconds = 0;

    if (!empty($this->time_log)) {

        foreach ($this->time_log as $log) {
            $start = Carbon::createFromFormat('H:i', $log['start_time']);
            $end   = Carbon::createFromFormat('H:i', $log['end_time']);

            if ($end->lt($start)) {
                $end->addDay();
            }

            if ($end->eq($start)) {
                continue;
            }

            $totalSeconds += $start->diffInSeconds($end);
        }
    }

    return gmdate('H:i', $totalSeconds);
}

    public function getTotalBreakTimeAttribute()
{
    $totalSeconds = 0;

    if (!empty($this->break_log)) {
        
        foreach ($this->break_log as $log) {

            if (
                empty($log['start_time']) ||
                empty($log['end_time'])
            ) {
                continue;
            }

            $start = Carbon::createFromFormat('H:i', $log['start_time']);
            $end   = Carbon::createFromFormat('H:i', $log['end_time']);

            // Handle past-midnight
            if ($end->lt($start)) {
                $end->addDay();
            }

            $diffSeconds = $end->diffInSeconds($start);

            // Ignore zero or negative breaks
            if ($diffSeconds > 0) {
                $totalSeconds += $diffSeconds;
            }
        }
    }

    return gmdate('H:i', $totalSeconds);
}

    public function getTotalWorkingTimeAttribute()
    {
        $employeeId = $this->employee_id;
        $sessionDate = $this->date;
        $workingSeconds = 0;

        if ($employeeId && $sessionDate) {
            // Fetch timesheets for this employee where dates array contains this session's date
            $timesheets = \App\Models\Timesheet::where('employee_id', $employeeId)
                ->where('dates.date', $sessionDate)
                ->get();

            foreach ($timesheets as $timesheet) {
                $dates = $timesheet->dates ?? [];
                foreach ($dates as $dateEntry) {
                    // Match the specific date
                    if (isset($dateEntry['date']) && $dateEntry['date'] === $sessionDate) {
                        $timeLogs = $dateEntry['time_log'] ?? [];
                        
                        foreach ($timeLogs as $log) {
                            if (!empty($log['start_time']) && !empty($log['end_time'])) {
                                try {
                                    $start = \Carbon\Carbon::createFromFormat('H:i', $log['start_time']);
                                    $end   = \Carbon\Carbon::createFromFormat('H:i', $log['end_time']);

                                    if ($end->lt($start)) {
                                        $end->addDay();
                                    }

                                    $workingSeconds += $start->diffInSeconds($end);
                                } catch (\Exception $e) {
                                    // Ignore if time format is invalid
                                }
                            }
                        }
                    }
                }
            }
        }

        return gmdate('H:i', $workingSeconds);
    }






    public function getIsLogoutAttribute()
    {
        return $this->attributes['is_logout'] ?? false;
    }

}
