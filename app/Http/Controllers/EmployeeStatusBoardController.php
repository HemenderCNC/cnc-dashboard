<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginSession;
use App\Models\Leave;
use App\Models\Timesheet;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EmployeeStatusBoardController extends Controller
{
    public function getStatusBoard(Request $request)
    {
        try {
            $today = Carbon::now()->toDateString();
            
            // Get all active users with their department relation
            $users = User::with(['department'])->get();

            // Fetch today's login sessions
            $sessions = LoginSession::where('date', $today)->get()->keyBy('employee_id');

            // Fetch today's active approved leaves
            $leaves = Leave::where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->whereIn('status', ['approved', 'Approved'])
                ->get()
                ->keyBy('employee_id');

            // Fetch running timesheets to identify who is in a meeting
            $runningTimesheets = Timesheet::where('status', 'running')->get()->groupBy('employee_id');

            // Groups for columns
            $notLoggedIn = [];
            $loggedOut = [];
            $available = [];
            $onBreak = [];
            $inMeeting = [];
            $workingOnTask = [];
            $leaveToday = [];

            foreach ($users as $user) {
                $userId = (string) $user->_id;
                
                // Get initials
                $firstName = $user->name ?? '';
                $lastName = $user->last_name ?? '';
                $initials = '';
                if (!empty($firstName)) {
                    $initials .= strtoupper(substr($firstName, 0, 1));
                }
                if (!empty($lastName)) {
                    $initials .= strtoupper(substr($lastName, 0, 1));
                }
                if (empty($initials)) {
                    $initials = strtoupper(substr($user->username ?? 'EE', 0, 2));
                }
                
                $empData = [
                    'id' => $userId,
                    'initials' => $initials,
                    'name' => trim($firstName . ' ' . $lastName),
                    'dept' => $user->department ? $user->department->name : 'N/A',
                ];

                // 1. Check if user is on leave today
                if ($leaves->has($userId)) {
                    $leaveToday[] = $empData;
                    continue;
                }

                // Get user's session today
                $session = $sessions->get($userId);

                // 2. Check if user has no session today (never logged in today)
                if (!$session) {
                    $notLoggedIn[] = $empData;
                    continue;
                }

                // 3. Check if user logged out today
                if ($session->is_logout || !empty($session->actual_check_out_time)) {
                    $loggedOut[] = $empData;
                    continue;
                }

                // 4. Check if user is on break
                if ($session->break === true) {
                    $onBreak[] = $empData;
                    continue;
                }

                // 5. Check if user has a running task
                $userRunningTasks = $runningTimesheets->get($userId);
                $runningTaskType = null;
                if ($userRunningTasks) {
                    foreach ($userRunningTasks as $task) {
                        if (isset($task->task_type)) {
                            $runningTaskType = strtolower(trim($task->task_type));
                            break;
                        }
                    }
                }

                if ($runningTaskType === 'meeting') {
                    $inMeeting[] = $empData;
                } elseif ($runningTaskType === 'r&d' || $runningTaskType === null) {
                    $available[] = $empData;
                } else {
                    $workingOnTask[] = $empData;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    [
                        'id' => 'not_logged_in',
                        'title' => "Not logged\nin today",
                        'count' => count($notLoggedIn),
                        'employees' => $notLoggedIn,
                    ],
                    [
                        'id' => 'leave',
                        'title' => "Leave today",
                        'count' => count($leaveToday),
                        'employees' => $leaveToday,
                    ],
                    [
                        'id' => 'available',
                        'title' => "Available\n(R&D)",
                        'count' => count($available),
                        'employees' => $available,
                    ],
                    [
                        'id' => 'on_break',
                        'title' => "On break",
                        'count' => count($onBreak),
                        'employees' => $onBreak,
                    ],
                    [
                        'id' => 'in_meeting',
                        'title' => "In meeting",
                        'count' => count($inMeeting),
                        'employees' => $inMeeting,
                    ],
                    [
                        'id' => 'working',
                        'title' => "Working on\nTask",
                        'count' => count($workingOnTask),
                        'employees' => $workingOnTask,
                    ],
                    [
                        'id' => 'logged_out',
                        'title' => "Logged out",
                        'count' => count($loggedOut),
                        'employees' => $loggedOut,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee status board data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
