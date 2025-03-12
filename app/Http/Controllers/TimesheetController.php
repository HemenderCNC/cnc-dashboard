<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Timesheet;
use App\Models\Tasks;
use App\Models\LoginSession;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TimesheetController extends Controller
{
    // Get all timesheets for an employee
    public function index(Request $request)
    {
        $matchStage = [];

        // Filter by Employee
        if ($request->user->role->name === 'Employee') {
            $user_id = $request->user->id;
            $matchStage['employee_id'] = $user_id;
        }
        if ($request->filled('employee_id')) {
            $matchStage['employee_id'] = $request->employee_id;
        }

        // Filter by Project
        if ($request->filled('project_id')) {
            $matchStage['project_id'] = $request->project_id;
        }

        // Filter by Date
        if ($request->filled('date')) {
            $matchStage['date'] = $request->date;
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $matchStage['date']  = [
                '$gte' => $startDate,
                '$lte' => $endDate
            ];
        }
        // Search by Task Name (Partial Match)
        if ($request->filled('task_name')) {
            $taskName = $request->task_name;

            // Fetch Task IDs matching the search query
            $taskIds = Tasks::where('title', 'like', "%{$taskName}%")
                ->pluck('id') // Ensure using MongoDB `_id`
                ->toArray();

            if (!empty($taskIds)) {
                $matchStage['task_id'] = ['$in' => $taskIds];
            } else {
                return response()->json(['message' => 'No tasks found matching the title.'], 404);
            }
        }

        // Ensure $matchStage is an object if empty
        if (empty($matchStage)) {
            $matchStage = (object)[];
        }

        // MongoDB Aggregation Pipeline
        $timesheets = Timesheet::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],

                // Lookup Task
                ['$lookup' => [
                    'from' => 'tasks',
                    'let' => ['taskId' => ['$toObjectId' => '$task_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskId']]]],
                        ['$lookup' => [
                            'from' => 'task_types',
                            'let' => ['taskTypeId' => ['$toObjectId' => '$task_type_id']],
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskTypeId']]]],
                                ['$project' => ['_id' => 0, 'name' => 1]]
                            ],
                            'as' => 'task_type'
                        ]],
                    ],
                    'as' => 'task'
                ]],

                // Lookup Project
                ['$lookup' => [
                    'from' => 'projects',
                    'let' => ['projectId' => ['$toObjectId' => '$project_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$projectId']]]]
                    ],
                    'as' => 'project'
                ]],

                // Lookup User
                ['$lookup' => [
                    'from' => 'users',
                    'let' => ['userId' => ['$toObjectId' => '$employee_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$userId']]]]
                    ],
                    'as' => 'user'
                ]],

                // Extract first start_time and last end_time from time_log array
                ['$addFields' => [
                    'start_time' => ['$arrayElemAt' => ['$time_log.start_time', 0]], // First start_time
                    'end_time' => [
                        '$arrayElemAt' => ['$time_log.end_time', -1] // Last end_time
                    ]
                ]],

                // Process total time spent in minutes
                ['$addFields' => [
                    'total_time_spent_minutes' => [
                        '$sum' => [
                            '$map' => [
                                'input' => '$time_log',
                                'as' => 'log',
                                'in' => [
                                    '$let' => [
                                        'vars' => [
                                            'start' => [
                                                '$dateFromParts' => [
                                                    'year' => 2025, 'month' => 3, 'day' => 6,
                                                    'hour' => ['$toInt' => ['$substr' => ['$$log.start_time', 0, 2]]],
                                                    'minute' => ['$toInt' => ['$substr' => ['$$log.start_time', 3, 2]]]
                                                ]
                                            ],
                                            'end' => [
                                                '$dateFromParts' => [
                                                    'year' => 2025, 'month' => 3, 'day' => 6,
                                                    'hour' => ['$toInt' => ['$substr' => ['$$log.end_time', 0, 2]]],
                                                    'minute' => ['$toInt' => ['$substr' => ['$$log.end_time', 3, 2]]]
                                                ]
                                            ]
                                        ],
                                        'in' => [
                                            '$divide' => [
                                                ['$subtract' => ['$$end', '$$start']],
                                                60000 // Convert milliseconds to minutes
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]],

                // Convert minutes to HH:MM format
                ['$addFields' => [
                    'total_hours' => ['$floor' => ['$divide' => ['$total_time_spent_minutes', 60]]],
                    'total_minutes' => ['$mod' => ['$total_time_spent_minutes', 60]]
                ]],

                // Fixing the $concat syntax issue by replacing {} with []
                ['$addFields' => [
                    'total_time_spent' => [
                        '$concat' => [
                            ['$toString' => '$total_hours'],
                            ':',
                            ['$toString' => [
                                '$cond' => [
                                    'if' => ['$lt' => ['$total_minutes', 10]],
                                    'then' => ['$concat' => ['0', ['$toString' => '$total_minutes']]],
                                    'else' => ['$toString' => '$total_minutes']
                                ]
                            ]]
                        ]
                    ]
                ]],

                // Final projection
                ['$project' => [
                    '_id' => 1,
                    'employee_id' => 1,
                    'date' => 1,
                    'time_log' => 1,
                    'start_time' => 1,
                    'end_time' => 1,
                    'total_time_spent' => 1,
                    'work_description' => 1,
                    'status' => 1,
                    'project' => 1,
                    'task' => 1,
                    'user' => 1,
                ]]
            ]);
        });

        return response()->json($timesheets, 200);


    }

    // Store new timesheet entry
    public function store(Request $request)
    {
        $userId = $request->user->id;
        $currentDate = Carbon::now()->toDateString();
        $session = LoginSession::where('employee_id', $userId)
            ->where('date', $currentDate)
            ->first();
            if ($session) {
                if ($session->break === true && !empty($session->break_log)) {
                    $session->break = false;
                    $session->save();
                }
            }
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'required|exists:tasks,id',
            'work_description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $timesheet = Timesheet::where('date', $currentDate)->where('task_id', $request->task_id)->first();
        if ($timesheet) {
            return response()->json(['message' => 'Timesheet already created.'], 401);
        }

        $time_log[] = array(
            'start_time' => now()->format('H:i'),
            'end_time' => now()->addMinute()->format('H:i'),
        );
        Timesheet::where('employee_id', $request->user->id)
            ->update(['status' => 'paused']);
        $timesheet = Timesheet::create([
            'project_id' => $request->project_id,
            'task_id' => $request->task_id,
            'date' => now()->format('Y-m-d'),
            'time_log' => $time_log,
            'work_description' => $request->work_description,
            'employee_id' => $request->user->id,
            'status' => 'running',
        ]);

        return response()->json($timesheet, 201);
    }

    public function stopTask(Request $request,$id){
        $timesheet = Timesheet::where('id', $id)->first();
        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $timesheet->status = 'paused';
        $timesheet->save();

        $userId = $request->user->id; // Get authenticated user ID
        $this->userBreakLogStart($userId);
        return response()->json($timesheet);
    }
    public function runTask(Request $request,$id){
        $timesheet = Timesheet::where('id', $id)->first();
        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $timesheet->status = 'running';
        $time_log = $timesheet->time_log;
        $time_log[] = array(
            'start_time' => now()->format('H:i'),
            'end_time' => now()->addMinute()->format('H:i'),
        );
        $timesheet->time_log = $time_log;
        $userId = $request->user->id; // Get authenticated user ID
        $this->userBreakLogStop($userId);
        $timesheet->save();
        return response()->json($timesheet);
    }
    public function completeTask(Request $request,$id){
        $userId = $request->user->id;
        $timesheet = Timesheet::where('id', $id)->first();
        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $timesheet->status = 'completed';
        $this->userBreakLogStart($userId);
        $timesheet->save();
        return response()->json($timesheet);
    }
    public function startBreak(Request $request){
        $userId = $request->user->id;
        $this->userBreakLogStart($userId);
        return response()->json(['message' => 'Break Start'], 200);
    }
    public function stopBreak(Request $request){
        $userId = $request->user->id;
        $this->userBreakLogStop($userId);
        return response()->json(['message' => 'Break Stop'], 200);
    }
    public function userBreakLogStart($userId){
        $currentDate = Carbon::now()->toDateString();
        $session = LoginSession::where('employee_id', $userId)->where('date', $currentDate)->first();
        if ($session) {
            $session->break = true;
            $breakLog = $session->break_log;
            $currentTime = Carbon::now()->format('H:i');
            $endTime = Carbon::now()->addMinute()->format('H:i');
            $breakLog[] = [
                'start_time' => $currentTime,
                'end_time' => $endTime,
            ];
            $session->break_log = $breakLog;
            $session->save();
        }
    }
    public function userBreakLogStop($userId){
        $currentDate = Carbon::now()->toDateString();
        $session = LoginSession::where('employee_id', $userId)->where('date', $currentDate)->first();
        if ($session) {
            $session->break = false;
            $session->save();
        }
    }
    // Show a specific timesheet entry
    public function show($id)
    {
        $timesheet = Timesheet::where('id', $id)->first();
        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        return response()->json($timesheet);
    }

    // Update timesheet entry
    public function update(Request $request, $id)
    {
        $timesheet = Timesheet::where('_id', $id)->first();
        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'required|exists:tasks,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',  // Ensures HH:MM format
            'end_time' => 'required|date_format:H:i|after:start_time',
            'work_description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $timesheet->update([
            'project_id' => $request->project_id,
            'task_id' => $request->task_id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'work_description' => $request->work_description,
        ]);
        return response()->json($timesheet,200);
    }

    // Delete timesheet entry
    public function destroy($id)
    {
        $timesheet = Timesheet::where('id', $id)->first();
        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $timesheet->delete();

        return response()->json(['message' => 'Timesheet entry deleted successfully.']);
    }
}
