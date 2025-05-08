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

    // Filter by Employee Role
    if ($request->user->role->name === 'employee') {
        $matchStage['employee_id'] = $request->user->id;
    }

    // Apply Filters
    foreach (['employee_id', 'project_id'] as $field) {
        if ($request->filled($field)) {
            $matchStage[$field] = $request->$field;
        }
    }

    // Date Filter (Inside `dates.date`)
    if ($request->filled('date')) {
        $matchStage['dates.date'] = $request->date;
    }

    // Date Range Filter
    if ($request->has(['start_date', 'end_date'])) {
        $matchStage['dates.date'] = [
            '$gte' => $request->start_date,
            '$lte' => $request->end_date,
        ];
    }

    // Task Name Search
    if ($request->filled('task_name')) {
        $taskIds = Tasks::where('title', 'like', "%{$request->task_name}%")
            ->pluck('_id')
            ->toArray();

        if (!empty($taskIds)) {
            $matchStage['task_id'] = ['$in' => $taskIds];
        } else {
            return response()->json(['message' => 'No tasks found matching the title.'], 404);
        }
    }
    if (empty($matchStage)) {
        $matchStage = (object)[];
    }

    // MongoDB Aggregation Pipeline
    $timesheets = Timesheet::raw(function ($collection) use ($matchStage) {
        return $collection->aggregate([
            // 1. Apply match filters
            ['$match' => $matchStage],

            // 2. Unwind the dates array (if stored correctly, no need to convert type)
            ['$unwind' => '$dates'],

            // 3. Unwind the time_log array within each date
            ['$unwind' => '$dates.time_log'],

            // 4. (Optional) Lookup related collections if needed…
            ['$lookup' => [
                'from' => 'tasks',
                'let'  => ['taskId' => ['$toObjectId' => '$task_id']],
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
                    ]]
                ],
                'as' => 'task'
            ]],
            ['$lookup' => [
                'from' => 'projects',
                'let' => ['projectId' => ['$toObjectId' => '$project_id']],
                'pipeline' => [
                    ['$match' => ['$expr' => ['$eq' => ['$_id', '$$projectId']]]]
                ],
                'as' => 'project'
            ]],
            ['$lookup' => [
                'from' => 'users',
                'let' => ['userId' => ['$toObjectId' => '$employee_id']],
                'pipeline' => [
                    ['$match' => ['$expr' => ['$eq' => ['$_id', '$$userId']]]]
                ],
                'as' => 'user'
            ]],

            // 5. Build full datetime strings from the date and time fields
            ['$addFields' => [
                'start_time_full' => [
                    '$concat' => [
                        '$dates.date',    // e.g. "2025-03-26"
                        'T',
                        '$dates.time_log.start_time', // e.g. "16:04"
                        ':00'             // seconds appended
                    ]
                ],
                'end_time_full' => [
                    '$concat' => [
                        '$dates.date',
                        'T',
                        '$dates.time_log.end_time',
                        ':00'
                    ]
                ]
            ]],

            // 6. Convert the full datetime strings into date objects
            ['$addFields' => [
                'start_time_date' => [
                    '$dateFromString' => [
                        'dateString' => '$start_time_full',
                        'timezone' => 'UTC',
                        'onError' => null
                    ]
                ],
                'end_time_date' => [
                    '$dateFromString' => [
                        'dateString' => '$end_time_full',
                        'timezone' => 'UTC',
                        'onError' => null
                    ]
                ]
            ]],

            // 7. Compute the duration (in minutes) for each time_log entry
            ['$addFields' => [
                'duration_minutes' => [
                    '$divide' => [
                        [
                            '$subtract' => [
                                '$end_time_date',
                                '$start_time_date'
                            ]
                        ],
                        60000
                    ]
                ]
            ]],

            // 8. Group by timesheet _id and sum up all durations
            ['$group' => [
                '_id' => '$_id',
                'employee_id' => ['$first' => '$employee_id'],
                'work_description' => ['$first' => '$work_description'],
                'status' => ['$first' => '$status'],
                'total_time_spent_minutes' => ['$sum' => '$duration_minutes'],
                'created_at' => ['$first' => '$created_at'],
                // You can push back lookups if needed:
                'task' => ['$first' => '$task'],
                'project' => ['$first' => '$project'],
                'user' => ['$first' => '$user'],
                'dates' => ['$push' => '$dates'] // optional, to return all date entries
            ]],

            // 9. Convert total minutes into HH:MM format
            ['$addFields' => [
                'total_hours' => ['$floor' => ['$divide' => ['$total_time_spent_minutes', 60]]],
                'total_minutes' => ['$mod' => ['$total_time_spent_minutes', 60]]
            ]],
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
            ['$sort' => ['created_at' => -1]],
            // 10. Final projection
            ['$project' => [
                '_id' => 1,
                'employee_id' => 1,
                'dates' => 1,
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


    // Get all timesheets for an employee
    public function myTimesheet(Request $request)
{
    $matchStage = [];

    // Filter by Employee Role
    // if ($request->user->role->name === 'employee') {
        $matchStage['employee_id'] = $request->user->id;
    // }

    // Apply Filters
    foreach (['employee_id', 'project_id'] as $field) {
        if ($request->filled($field)) {
            $matchStage[$field] = $request->$field;
        }
    }

    // Date Filter (Inside `dates.date`)
    if ($request->filled('date')) {
        $matchStage['dates.date'] = $request->date;
    }

    // Date Range Filter
    if ($request->has(['start_date', 'end_date'])) {
        $matchStage['dates.date'] = [
            '$gte' => $request->start_date,
            '$lte' => $request->end_date,
        ];
    }

    // Task Name Search
    if ($request->filled('task_name')) {
        $taskIds = Tasks::where('title', 'like', "%{$request->task_name}%")
            ->pluck('_id')
            ->toArray();

        if (!empty($taskIds)) {
            $matchStage['task_id'] = ['$in' => $taskIds];
        } else {
            return response()->json(['message' => 'No tasks found matching the title.'], 404);
        }
    }
    if (empty($matchStage)) {
        $matchStage = (object)[];
    }

    // MongoDB Aggregation Pipeline
    $timesheets = Timesheet::raw(function ($collection) use ($matchStage) {
        return $collection->aggregate([
            // 1. Apply match filters
            ['$match' => $matchStage],

            // 2. Unwind the dates array (if stored correctly, no need to convert type)
            ['$unwind' => '$dates'],

            // 3. Unwind the time_log array within each date
            ['$unwind' => '$dates.time_log'],

            // 4. (Optional) Lookup related collections if needed…
            ['$lookup' => [
                'from' => 'tasks',
                'let'  => ['taskId' => ['$toObjectId' => '$task_id']],
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
                    ]]
                ],
                'as' => 'task'
            ]],
            ['$lookup' => [
                'from' => 'projects',
                'let' => ['projectId' => ['$toObjectId' => '$project_id']],
                'pipeline' => [
                    ['$match' => ['$expr' => ['$eq' => ['$_id', '$$projectId']]]]
                ],
                'as' => 'project'
            ]],
            ['$lookup' => [
                'from' => 'users',
                'let' => ['userId' => ['$toObjectId' => '$employee_id']],
                'pipeline' => [
                    ['$match' => ['$expr' => ['$eq' => ['$_id', '$$userId']]]]
                ],
                'as' => 'user'
            ]],

            // 5. Build full datetime strings from the date and time fields
            ['$addFields' => [
                'start_time_full' => [
                    '$concat' => [
                        '$dates.date',    // e.g. "2025-03-26"
                        'T',
                        '$dates.time_log.start_time', // e.g. "16:04"
                        ':00'             // seconds appended
                    ]
                ],
                'end_time_full' => [
                    '$concat' => [
                        '$dates.date',
                        'T',
                        '$dates.time_log.end_time',
                        ':00'
                    ]
                ]
            ]],

            // 6. Convert the full datetime strings into date objects
            ['$addFields' => [
                'start_time_date' => [
                    '$dateFromString' => [
                        'dateString' => '$start_time_full',
                        'timezone' => 'UTC',
                        'onError' => null
                    ]
                ],
                'end_time_date' => [
                    '$dateFromString' => [
                        'dateString' => '$end_time_full',
                        'timezone' => 'UTC',
                        'onError' => null
                    ]
                ]
            ]],

            // 7. Compute the duration (in minutes) for each time_log entry
            ['$addFields' => [
                'duration_minutes' => [
                    '$divide' => [
                        [
                            '$subtract' => [
                                '$end_time_date',
                                '$start_time_date'
                            ]
                        ],
                        60000
                    ]
                ]
            ]],

            // 8. Group by timesheet _id and sum up all durations
            ['$group' => [
                '_id' => '$_id',
                'employee_id' => ['$first' => '$employee_id'],
                'work_description' => ['$first' => '$work_description'],
                'status' => ['$first' => '$status'],
                'total_time_spent_minutes' => ['$sum' => '$duration_minutes'],
                'created_at' => ['$first' => '$created_at'],
                // You can push back lookups if needed:
                'task' => ['$first' => '$task'],
                'project' => ['$first' => '$project'],
                'user' => ['$first' => '$user'],
                'dates' => ['$push' => '$dates'] // optional, to return all date entries
            ]],

            // 9. Convert total minutes into HH:MM format
            ['$addFields' => [
                'total_hours' => ['$floor' => ['$divide' => ['$total_time_spent_minutes', 60]]],
                'total_minutes' => ['$mod' => ['$total_time_spent_minutes', 60]]
            ]],
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
            ['$sort' => ['created_at' => -1]],
            // 10. Final projection
            ['$project' => [
                '_id' => 1,
                'employee_id' => 1,
                'dates' => 1,
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


public function employeetimeline($employeeId, Request $request)
{
    // Retrieve start_date and end_date from the query parameters (if provided)
    $startDate = $request->query('start_date');
    $endDate = $request->query('end_date');

    $matchStage = ['employee_id' => $employeeId];

    // Add date range filter if start_date and end_date are provided
    if ($startDate && $endDate) {
        $matchStage['dates.date'] = [
            '$gte' => $startDate,
            '$lte' => $endDate,
        ];
    }

    // MongoDB Aggregation Pipeline
    $pipeline = [
        ['$match' => $matchStage], // Apply the dynamic match stage with date filter

        ['$unwind' => '$dates'],
        ['$unwind' => '$dates.time_log'],

        // Lookup task names from the tasks collection
        ['$lookup' => [
            'from' => 'tasks',
            'let' => ['taskId' => ['$toObjectId' => '$task_id']], // Convert task_id to ObjectId
            'pipeline' => [
                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskId']]]],
                ['$project' => ['title' => 1]] // Only include the title field
            ],
            'as' => 'task'
        ]],

        // Add a field for task name
        ['$addFields' => [
            'task_name' => ['$arrayElemAt' => ['$task.title', 0]]
        ]],

        ['$group' => [
            '_id' => '$dates.date',
            'start_times' => ['$push' => '$dates.time_log.start_time'],
            'end_times' => ['$push' => '$dates.time_log.end_time'],
            'total_minutes' => ['$sum' => [
                '$let' => [
                    'vars' => [
                        'start' => [
                            '$dateFromString' => [
                                'dateString' => [
                                    '$concat' => ['2024-01-01T', '$dates.time_log.start_time', ':00']
                                ]
                            ]
                        ],
                        'end' => [
                            '$dateFromString' => [
                                'dateString' => [
                                    '$concat' => ['2024-01-01T', '$dates.time_log.end_time', ':00']
                                ]
                            ]
                        ]
                    ],
                    'in' => [
                        '$divide' => [
                            ['$subtract' => ['$$end', '$$start']],
                            1000 * 60
                        ]
                    ]
                ]
            ]],
            'task_names' => ['$addToSet' => '$task_name']
        ]],

        ['$project' => [
            'date' => '$_id',
            'start_time' => ['$min' => '$start_times'],
            'end_time' => ['$max' => '$end_times'],
            'total_minutes' => 1,
            'hours' => ['$floor' => ['$divide' => ['$total_minutes', 60]]],
            'minutes' => ['$mod' => ['$total_minutes', 60]],
            'working_hours' => [
                '$concat' => [
                    ['$toString' => ['$floor' => ['$divide' => ['$total_minutes', 60]]]],
                    'h ',
                    ['$toString' => ['$mod' => ['$total_minutes', 60]]],
                    'm'
                ]
            ],
            'day_type' => [
                '$switch' => [
                    'branches' => [
                        [
                            'case' => ['$lt' => ['$total_minutes', 240]],
                            'then' => 'half_day'
                        ],
                        [
                            'case' => ['$gte' => ['$total_minutes', 480]],
                            'then' => 'full_day'
                        ]
                    ],
                    'default' => 'less_than_half_day'
                ]
            ],
            'task_names' => 1
        ]],

        ['$sort' => ['date' => 1]]
    ];

    // Execute the aggregation pipeline
    $timeline = Timesheet::raw(function ($collection) use ($pipeline) {
        return $collection->aggregate($pipeline);
    });

    return response()->json($timeline);
}
    // Store new timesheet entry
    public function store(Request $request)
    {
        $userId = $request->user->id;
        $currentDate = Carbon::now()->toDateString();

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,_id',
            'task_id' => 'required|exists:tasks,_id',
            'work_description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if a timesheet already exists for this task and date
        $timesheet = Timesheet::where('task_id', $request->task_id)
            ->where('employee_id', $userId)
            ->first();

            $timeLogEntry = [
                'start_time' => now()->format('H:i'),
                'end_time' => now()->addMinute()->format('H:i'),
            ];
            $datesArray = [[
                'date' => $currentDate,
                'time_log' => [$timeLogEntry],
            ]];
            if ($timesheet) {
                // // Decode dates if it's stored as a string (for existing records)
                // if (is_string($timesheet->dates)) {
                //     $timesheet->dates = json_decode($timesheet->dates, true);
                // }

                // // Ensure it's an array
                // $existingDates = is_array($timesheet->dates) ? $timesheet->dates : [];

                // // Check if the current date already exists
                // $existingDateKey = array_search($currentDate, array_column($existingDates, 'date'));

                // if ($existingDateKey !== false) {
                //     // Append the new time log to the existing date
                //     $existingDates[$existingDateKey]['time_log'][] = $timeLogEntry;
                // } else {
                //     // Add a new date entry with time log
                //     $existingDates[] = [
                //         'date' => $currentDate,
                //         'time_log' => [$timeLogEntry],
                //     ];
                // }

                // $timesheet->dates = $existingDates;
                // $timesheet->status = 'running';
                // $timesheet->save();
                return response()->json(['message' => 'Task already created.'], 404);
            } else {
                // Create a new timesheet entry
                $timesheet = Timesheet::create([
                    'project_id' => $request->project_id,
                    'task_id' => $request->task_id,
                    'employee_id' => $userId,
                    'dates' => [
                        [
                            'date' => $currentDate,
                            'time_log' => [
                                [
                                    'start_time' => now()->format('H:i'),
                                    'end_time' => now()->addMinute()->format('H:i'),
                                ]
                            ],
                        ]
                    ], // Store as a plain PHP array
                    'work_description' => $request->work_description,
                    'status' => 'running',
                ]);
            }

        // Pause any other running timesheets for the user
        Timesheet::where('employee_id', $userId)
            ->where('_id', '!=', $timesheet->_id)
            ->update(['status' => 'paused']);

        // Handle break session in LoginSession
        $session = LoginSession::where('employee_id', $userId)
            ->where('date', $currentDate)
            ->first();

        if ($session && $session->break === true && !empty($session->break_log)) {
            $session->break = false;
            $session->save();
        }

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
    public function runTask(Request $request, $id)
{
    $timesheet = Timesheet::where('_id', $id)->first();

    if (!$timesheet) {
        return response()->json(['message' => 'Timesheet not found'], 404);
    }

    $currentDate = Carbon::now()->toDateString();
    $userId = $request->user->id;

    // Get the last updated time from the timesheet (before any modifications)
    $lastUpdated = Carbon::parse($timesheet->updated_at);
    $now = Carbon::now();
    $diffMinutes = $lastUpdated->diffInMinutes($now);

    // Ensure `dates` is an array
    $dates = $timesheet->dates ?? [];

    // Find the entry for today's date
    $existingDateKey = array_search($currentDate, array_column($dates, 'date'));

    if ($existingDateKey !== false) {
        // Today's entry exists.
        $timeLogs = $dates[$existingDateKey]['time_log'] ?? [];
        if (!empty($timeLogs)) {
            if ($diffMinutes >= 5) {
                // If more than 5 minutes have passed, create a new time log entry.
                $timeLogs[] = [
                    'start_time' => $now->format('H:i'),
                    'end_time'   => $now->copy()->addMinute()->format('H:i'),
                ];
            } else {
                // Otherwise, update the end_time of the last log.
                $lastIndex = count($timeLogs) - 1;
                $timeLogs[$lastIndex]['end_time'] = $now->copy()->addMinute()->format('H:i');
            }
        } else {
            // If there are no time logs, create one.
            $timeLogs[] = [
                'start_time' => $now->format('H:i'),
                'end_time'   => $now->copy()->addMinute()->format('H:i'),
            ];
        }
        $dates[$existingDateKey]['time_log'] = $timeLogs;
    } else {
        // If today's date does not exist, create a new entry with a new time log.
        $dates[] = [
            'date'     => $currentDate,
            'time_log' => [[
                'start_time' => $now->format('H:i'),
                'end_time'   => $now->copy()->addMinute()->format('H:i'),
            ]]
        ];
    }

    // Assign modified dates array back to the model and update status.
    $timesheet->dates = $dates;
    $timesheet->status = 'running';
    $timesheet->save();

    // Pause any other running tasks for the same employee.
    Timesheet::where('employee_id', $userId)
        ->where('_id', '!=', $timesheet->_id)
        ->update(['status' => 'paused']);

    // Stop break log if active.
    $this->userBreakLogStop($userId);

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
    // public function userBreakLogStart($userId){
    //     $currentDate = Carbon::now()->toDateString();
    //     $session = LoginSession::where('employee_id', $userId)->where('date', $currentDate)->first();
    //     if ($session) {
    //         $session->break = true;
    //         $breakLog = $session->break_log;
    //         $currentTime = Carbon::now()->format('H:i');
    //         $endTime = Carbon::now()->addMinute()->format('H:i');
    //         $breakLog[] = [
    //             'start_time' => $currentTime,
    //             'end_time' => $endTime,
    //         ];
    //         $session->break_log = $breakLog;
    //         $session->save();
    //     }
    // }
    public function userBreakLogStart($userId)
    {
        $currentDate = Carbon::now()->toDateString();
        $currentTime = Carbon::now()->format('H:i');
    
        $session = LoginSession::where('employee_id', $userId)->where('date', $currentDate)->first();
    
        if ($session) {
            $breakLog = $session->break_log ?? [];
    
            // Check if an entry with the same start_time already exists
            $existingEntry = collect($breakLog)->firstWhere('start_time', Carbon::now()->subMinute()->format('H:i'));
    
            if ($existingEntry) {
                // If an entry with the same start_time exists, do nothing
                // return;
                            // If no entry exists, execute the current code
                    $session->break = true;
                    $endTime = Carbon::now()->addMinute()->format('H:i');
                    // $endTime = Carbon::now()->format('H:i');
                    $breakLog[] = [
                        'start_time' => Carbon::now()->subMinute()->format('H:i'),
                        'end_time' => Carbon::now()->subMinute()->format('H:i'),
                    ];
                    $session->break_log = $breakLog;
                    $session->save();
            }else{
                            // If no entry exists, execute the current code
                    $session->break = true;
                    $endTime = Carbon::now()->addMinute()->format('H:i');
                    // $endTime = Carbon::now()->format('H:i');
                    $breakLog[] = [
                        'start_time' => Carbon::now()->subMinute()->format('H:i'),
                        'end_time' => Carbon::now()->format('H:i'),
                    ];
                    $session->break_log = $breakLog;
                    $session->save();
            }
    

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
            // 'start_time' => $request->start_time,
            // 'end_time' => $request->end_time,
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
