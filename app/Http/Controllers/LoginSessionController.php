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
    // public function trackSession(Request $request)
    // {
    //     $userId = $request->user->id;
    //     $this->trackLoginSessions($userId);
    //     $this->trackTimesheetSession($userId);

    //     return response()->json(['message' => 'Session tracked successfully'], 200);
    // }

    public function trackSession(Request $request)
    {
        $userId = $request->user->id;
        $this->trackTimesheetSession($userId);
        $this->trackLoginSessions($userId);
        // Call the index function of GeneralSettingsController
        $generalSettings = app(GeneralSettingsController::class)->index($request);

        return response()->json([
            'message' => 'Session tracked successfully',
            'general_settings' => $generalSettings
        ], 200);
    }
    public function trackLoginSessions($userId)
    {
        $currentDate = Carbon::now()->toDateString(); // Uses default timezone from config
        $currentTime = Carbon::now()->format('H:i');
        $endTime = Carbon::now()->addMinute()->format('H:i');
        $session = LoginSession::where('employee_id', $userId)
            ->where('date', $currentDate)
            ->first();
        if ($session) {
            $lastUpdatedTime = Carbon::parse($session->updated_at)->timestamp;
            $now = Carbon::now()->timestamp;
            // if (($now - $lastUpdatedTime) < 60) {
            //     return response()->json(['message' => 'Already updated recently'], 200);
            // }

            $timeLog = $session->time_log ?? [];
            if (!empty($timeLog) && ($now - $lastUpdatedTime) >= 300) {
                $timeLog[] = [
                    'start_time' => Carbon::now()->format('H:i'),
                    'end_time' => Carbon::now()->format('H:i'),
                ];
            } else if (!empty($timeLog)) {
                $lastIndex = count($timeLog) - 1;
                $timeLog[$lastIndex]['end_time'] = Carbon::now()->format('H:i');
            } else {
                $timeLog[] = [
                    'start_time' => Carbon::now()->format('H:i'),
                    'end_time' => Carbon::now()->format('H:i'),
                ];
            }
            $session->time_log = $timeLog;
            $session->save();

            if ($session->break === true) {
                $breakLog = $session->break_log ?? [];
                if (!empty($breakLog) && ($now - $lastUpdatedTime) >= 300) {
                    $breakLog[] = [
                        'start_time' => Carbon::now()->format('H:i'),
                        'end_time' => Carbon::now()->format('H:i'),
                    ];
                } else if (!empty($breakLog)) {
                    // Update the end_time of the last break log entry
                    $lastIndex = count($breakLog) - 1;
                    $breakLog[$lastIndex]['end_time'] = Carbon::now()->format('H:i');
                } else {
                    // First break log entry
                    $breakLog[] = [
                        'start_time' => Carbon::now()->format('H:i'),
                        'end_time' => Carbon::now()->format('H:i'),
                    ];
                }

                $session->break_log = $breakLog;
                $session->save();
            }
        } else {
            $breakStatus = true;
            $timesheet = Timesheet::where('employee_id', $userId)
                ->where('status', 'running')
                ->first();
            if ($timesheet) {
                $breakStatus = false;
            }
            /**
             * END FIX
             */
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
                'break' => $breakStatus,
                'break_log' => [
                    [
                        'start_time' => $currentTime,
                        'end_time' => $currentTime,
                    ]
                ],
            ]);
        }
    }
    public function trackTimesheetSession($userId)
    {
        $currentDate = Carbon::now()->toDateString(); // e.g. "2025-03-26"

        // Retrieve the running timesheet for this user.
        $timesheet = Timesheet::where('employee_id', $userId)
            ->where('status', 'running')
            ->first();

        if (!$timesheet) {
            return;
        }
        $session = LoginSession::where('employee_id', $userId)
            ->where('date', $currentDate)
            ->first();
        if ($session && $session->break === true) {
            $session->break = false;
            $session->save();
        }
        $lastUpdatedTime = Carbon::parse($timesheet->updated_at)->timestamp;
        $now = Carbon::now()->timestamp;
        if (($now - $lastUpdatedTime) < 60) {
            return response()->json(['message' => 'Already updated recently'], 200);
        }
        // Get the timesheet's last updated time BEFORE we make any changes.
        $lastUpdated = Carbon::parse($timesheet->updated_at);
        $now = Carbon::now();
        $diffMinutes = $lastUpdated->diffInMinutes($now);

        // Ensure the timesheet dates are available as an array.
        $dates = $timesheet->dates ?? [];

        // Find today's entry in the dates array.
        $existingDateKey = array_search($currentDate, array_column($dates, 'date'));

        // Format the current time values.
        $formattedStart = $now->format('H:i');
        $formattedEnd = $now->copy()->format('H:i');

        if ($existingDateKey !== false) {
            // Today's date entry exists.
            $timeLogs = $dates[$existingDateKey]['time_log'] ?? [];
            if (!empty($timeLogs)) {
                // Decide if we need to create a new time log or update the last one.
                if ($diffMinutes >= 5) {
                    // More than 5 minutes since last update; append a new time log.
                    $timeLogs[] = [
                        'start_time' => $formattedStart,
                        'end_time'   => $formattedEnd,
                    ];
                } else {
                    // Less than 5 minutes; update the end_time of the last log.
                    $lastIndex = count($timeLogs) - 1;
                    $timeLogs[$lastIndex]['end_time'] = $formattedEnd;
                }
            } else {
                // No time logs exist for today; create one.
                $timeLogs[] = [
                    'start_time' => $formattedStart,
                    'end_time'   => $formattedEnd,
                ];
            }
            // Update today's date entry with the new/updated time logs.
            $dates[$existingDateKey]['time_log'] = $timeLogs;
        } else {
            // No entry for today; create a new one.
            $dates[] = [
                'date'     => $currentDate,
                'time_log' => [[
                    'start_time' => $formattedStart,
                    'end_time'   => $formattedEnd,
                ]]
            ];
        }

        // Assign the modified dates array back to the timesheet and save.
        $timesheet->dates = $dates;
        $timesheet->save();
    }

    public function attendance(Request $request)
    {
        $now = Carbon::now(); // Get current date
        // Get working days (Monday-Friday) excluding Saturday & Sunday
        $startDate = $now->startOfMonth();
        $endDate = $now->copy()->endOfMonth();
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
        }
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
        if ($request->user->role->slug === 'employee') {
            $query->where('employee_id', $request->user->id);
        } else if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        $session_logs = $query->get();
        $session_count = $query->count();

        $query = Leave::query();
        // If user is an Employee, restrict to their own records
        if ($request->user->role->slug === 'employee') {
            $query->where('employee_id', $request->user->id);
        } else if ($request->has('employee_id')) {
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
            'session_logs' => $session_logs
        ];
    }
}
