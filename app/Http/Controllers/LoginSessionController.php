<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoginSession;
use App\Models\Timesheet;
use App\Models\Holiday;
use App\Models\Leave;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
class LoginSessionController extends Controller
{
    public function trackSession(Request $request)
    {
        $userId = $request->user->id;
        $this->trackLoginSessions($userId);
        $this->trackTimesheetSession($userId);

        return response()->json(['message' => 'Session tracked successfully'], 200);
    }
    public function trackLoginSessions($userId){
        $currentDate = Carbon::now()->toDateString(); // Uses default timezone from config
        $currentTime = Carbon::now()->format('H:i');
        $endTime = Carbon::now()->addMinute()->format('H:i');
        $session = LoginSession::where('employee_id', $userId)
            ->where('date', $currentDate)
            ->first();
        if ($session) {
            $lastUpdatedTime = Carbon::parse($session->updated_at)->timestamp;
            $now = Carbon::now()->timestamp;
            if (($now - $lastUpdatedTime) < 60) {
                return response()->json(['message' => 'Already updated recently'], 200);
            }

            $timeLog = $session->time_log ?? [];
            if (!empty($timeLog) && ($now - $lastUpdatedTime) >= 300) {
                $timeLog[] = [
                    'start_time' => $currentTime,
                    'end_time' => $currentTime,
                ];
            }
            else{
                $lastIndex = count($timeLog) - 1;
                $timeLog[$lastIndex]['end_time'] = $currentTime;
            }
            $session->time_log = $timeLog;
            $session->save();

            if ($session->break === true && !empty($session->break_log)) {
                $breakLog = $session->break_log;
                $lastIndex = count($breakLog) - 1;
                if ($lastIndex >= 0) {
                    $breakLog[$lastIndex]['end_time'] = $currentTime;
                }
                $session->break_log = $breakLog;
            }
            $session->save();
        } else {
            // Create new session entry
            LoginSession::create([
                'employee_id' => $userId,
                'date' => $currentDate,
                'time_log' => [
                    [
                        'start_time' => $currentTime,
                        'end_time' => $currentTime,
                    ]
                ],
                'break' => true,
                'break_log' => [
                    [
                        'start_time' => $currentTime,
                        'end_time' => $endTime,
                    ]
                ],
            ]);
        }
    }
    public function trackTimesheetSession($userId){
        $currentDate = Carbon::now()->toDateString(); // Uses default timezone from config
        $timesheet = Timesheet::where('employee_id', $userId)
            ->where('date', $currentDate)
            ->where('status', 'running')
            ->first();
        if ($timesheet) {
            $time_log = $timesheet->time_log;
            $lastIndex = count($time_log) - 1;
            if ($lastIndex >= 0) {
                $time_log[$lastIndex]['end_time'] = Carbon::now()->addMinute()->format('H:i');
            }
            $timesheet->time_log = $time_log;
            $timesheet->save();
        }

    }
    public function attendance(Request $request){
        $now = Carbon::now(); // Get current date
        // Get working days (Monday-Friday) excluding Saturday & Sunday
        $startDate = $now->startOfMonth();
        $endDate = $now->copy()->endOfMonth();
        $workingDays = collect(CarbonPeriod::create($startDate, $endDate))
            ->filter(fn($date) => !in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]))
            ->count();
        $holidays = Holiday::whereBetween('festival_date', [$startDate->toDateString(), $endDate->toDateString()])
        ->get()
        ->filter(fn($holiday) => !in_array(Carbon::parse($holiday->festival_date)->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) // Exclude Sat-Sun holidays
        ->count();
        $adjustedWorkingDays = $workingDays - $holidays;
        $query = LoginSession::query()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
        if ($request->user->role->name === 'Employee') {
            $query->where('employee_id', $request->user->id);
        }
        else if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        $session_logs = $query->get();
        $session_count = $query->count();

        $query = Leave::query();
        // If user is an Employee, restrict to their own records
        if ($request->user->role->name === 'Employee') {
            $query->where('employee_id', $request->user->id);
        }
        else if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        $stdate = $startDate->toDateString();
        $enddate = $endDate->toDateString();
        $query->whereBetween('start_date', [$stdate, $enddate]);
        $total_leaves = $query->where('status', 'approved')->sum('leave_duration');
        $days_present = $session_count;
        return [
            'total_days' => $endDate->day,
            'total_working_days' => $adjustedWorkingDays,
            'total_holidays_excluding_weekends' => $holidays,
            'days_present' => $days_present,
            'total_leaves' => $total_leaves,
            'session_logs'=>$session_logs
        ];
    }
}
