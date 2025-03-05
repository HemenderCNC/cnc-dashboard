<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoginSession;
use App\Models\Timesheet;
use Carbon\Carbon;

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
            // $session->save();

            if ($session->break === true && !empty($session->break_log)) {
                $breakLog = $session->break_log;
                $lastIndex = count($breakLog) - 1;
                if ($lastIndex >= 0) {
                    $breakLog[$lastIndex]['end_time'] = $endTime;
                }
                $session->break_log = $breakLog;
            }
            // $session->save();
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
                $time_log[$lastIndex]['end_time'] = Carbon::parse($time_log[$lastIndex]['end_time'])->addMinute()->format('H:i');
            }
            $timesheet->time_log = $time_log;
            $timesheet->save();
        }

    }
}
