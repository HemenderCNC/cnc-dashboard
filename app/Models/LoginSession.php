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
    ];
    protected $appends = ['check_in_time', 'check_out_time', 'total_login_time','total_working_time','total_break_time'];

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
    $loginSeconds = 0;
    $breakSeconds = 0;

    foreach ($this->time_log ?? [] as $log) {
        $start = Carbon::createFromFormat('H:i', $log['start_time']);
        $end   = Carbon::createFromFormat('H:i', $log['end_time']);

        if ($end->lt($start)) {
            $end->addDay();
        }

        $loginSeconds += $start->diffInSeconds($end);
    }

    foreach ($this->break_log ?? [] as $log) {
        $start = Carbon::createFromFormat('H:i', $log['start_time']);
        $end   = Carbon::createFromFormat('H:i', $log['end_time']);

        if ($end->lt($start)) {
            $end->addDay();
        }

        $breakSeconds += $start->diffInSeconds($end);
    }

    $workingSeconds = max(0, $loginSeconds - $breakSeconds);

    return gmdate('H:i', $workingSeconds);
}

}
