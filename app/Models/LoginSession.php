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
        $totalMinutes = 0;

        if (!empty($this->time_log)) {
            foreach ($this->time_log as $log) {
                $start = Carbon::createFromFormat('H:i', $log['start_time']);
                $end = Carbon::createFromFormat('H:i', $log['end_time']);

                // Handle past-midnight checkout
                if ($end->lt($start)) {
                    $end->addDay();
                }

                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf("%02d:%02d", $hours, $minutes);
    }
    public function getTotalBreakTimeAttribute()
    {
        $totalMinutes = 0;

        if (!empty($this->break_log)) {
            foreach ($this->break_log as $log) {
                $start = Carbon::createFromFormat('H:i', $log['start_time']);
                $end = Carbon::createFromFormat('H:i', $log['end_time']);

                // Handle past-midnight checkout
                if ($end->lt($start)) {
                    $end->addDay();
                }

                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf("%02d:%02d", $hours, $minutes);
    }
    public function getTotalWorkingTimeAttribute()
    {
        $totalLoginMinutes = 0;
        $totalBreakMinutes = 0;

        // Calculate total login time
        if (!empty($this->time_log)) {
            foreach ($this->time_log as $log) {
                $start = Carbon::createFromFormat('H:i', $log['start_time']);
                $end = Carbon::createFromFormat('H:i', $log['end_time']);

                // Handle past-midnight checkout
                if ($end->lt($start)) {
                    $end->addDay();
                }

                $totalLoginMinutes += $start->diffInMinutes($end);
            }
        }

        // Calculate total break time
        if (!empty($this->break_log)) {
            foreach ($this->break_log as $break) {
                $start = Carbon::createFromFormat('H:i', $break['start_time']);
                $end = Carbon::createFromFormat('H:i', $break['end_time']);

                // Handle past-midnight break
                if ($end->lt($start)) {
                    $end->addDay();
                }

                $totalBreakMinutes += $start->diffInMinutes($end);
            }
        }

        // Calculate total working time (Login Time - Break Time)
        $totalWorkingMinutes = max(0, $totalLoginMinutes - $totalBreakMinutes);

        $hours = floor($totalWorkingMinutes / 60);
        $minutes = $totalWorkingMinutes % 60;

        return sprintf("%02d:%02d", $hours, $minutes);
    }
}
