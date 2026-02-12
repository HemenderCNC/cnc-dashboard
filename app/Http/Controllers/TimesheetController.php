<?php

namespace App\Http\Controllers;

use App\Models\HelpingHand;
use Illuminate\Http\Request;
use App\Models\Timesheet;
use App\Models\Tasks;
use App\Models\LoginSession;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Project;
use App\Models\Role;   
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use MongoDB\BSON\ObjectId;
use App\Notifications\PushNotification;

class TimesheetController extends Controller
{

    public function newTimesheetList(Request $request)
    {

        $matchStage = [];

        // Pagination setup
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);
        $skip = ($page - 1) * $limit;

        // Role check
        if ($request->user->role->slug === 'employee' || $request->user->role->slug === 'qa') {
            $matchStage['employee_id'] = $request->user->id;
        }

        // Basic filters
        foreach (['employee_id', 'project_id'] as $field) {
            if ($request->filled($field)) {
                $matchStage[$field] = $request->$field;
            }
        }

        // Exact date filter
        if ($request->filled('date')) {
            $matchStage['dates.date'] = $request->date;
        }

        // Date range filter
        if ($request->has(['start_date', 'end_date'])) {
            $matchStage['dates.date'] = [
                '$gte' => $request->start_date,
                '$lte' => $request->end_date,
            ];
        }

        // Task name search
        if ($request->filled('task_name')) {
            $tasks = Tasks::where('title', 'like', "%{$request->task_name}%")->get();
            $taskIds = Tasks::where('title', 'like', "%{$request->task_name}%")
                ->pluck('id')
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

        $aggregation = Timesheet::raw(function ($collection) use ($matchStage, $limit, $skip) {
            $pipeline = [
                ['$match' => $matchStage],
                ['$unwind' => '$dates'],
                ['$unwind' => '$dates.time_log'],

                // Pre-calculate time duration
                ['$addFields' => [
                    'start_time' => [
                        '$toDate' => [
                            '$concat' => ['$dates.date', 'T', '$dates.time_log.start_time', ':00']
                        ]
                    ],
                    'end_time' => [
                        '$toDate' => [
                            '$concat' => ['$dates.date', 'T', '$dates.time_log.end_time', ':00']
                        ]
                    ]
                ]],
                ['$addFields' => [
                    'duration_minutes' => [
                        '$divide' => [
                            ['$subtract' => ['$end_time', '$start_time']],
                            60000
                        ]
                    ]
                ]],

                // Removed grouping to separate time logs
                ['$addFields' => [
                    'total_time_spent_minutes' => '$duration_minutes',
                    'dates' => ['$dates']
                ]],

                // Join data (using simpler joins)
                ['$addFields' => [
                    'task_id' => ['$toObjectId' => '$task_id'],
                    'project_id' => ['$toObjectId' => '$project_id'],
                    'employee_id' => ['$toObjectId' => '$employee_id'],
                ]],

                ['$lookup' => [
                    'from' => 'tasks',
                    'localField' => 'task_id',
                    'foreignField' => '_id',
                    'as' => 'task'
                ]],
                
                [
                    '$addFields' => [
                        'task.task_type' => '$task_type'
                    ]
                ],
                ['$lookup' => [
                    'from' => 'projects',
                    'localField' => 'project_id',
                    'foreignField' => '_id',
                    'as' => 'project'
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'employee_id',
                    'foreignField' => '_id',
                    'as' => 'user'
                ]],


                // Format time string
                ['$addFields' => [
                    'total_hours' => ['$floor' => ['$divide' => ['$total_time_spent_minutes', 60]]],
                    'total_minutes' => ['$mod' => ['$total_time_spent_minutes', 60]],
                ]],
                ['$addFields' => [
                    'total_time_spent' => [
                        '$concat' => [
                            ['$toString' => '$total_hours'],
                            ':',
                            ['$cond' => [
                                'if' => ['$lt' => ['$total_minutes', 10]],
                                'then' => ['$concat' => ['0', ['$toString' => '$total_minutes']]],
                                'else' => ['$toString' => '$total_minutes']
                            ]]
                        ]
                    ]
                ]],
                ['$sort' => ['created_at' => -1]],
                ['$project' => [
                    '_id' => 1,
                    'employee_id' => 1,
                    'dates' => 1,
                    'total_time_spent' => 1,
                    'work_description' => 1,
                    'status' => 1,
                    'project' => [
                        '$map' => [
                            'input' => '$project',
                            'as' => 'p',
                            'in' => [
                                'project_name' => '$$p.project_name',
                                'id' => ['$toString' => '$$p._id']
                            ]
                        ]
                    ],
                    'task_type' => 1,
                    'task' => [
                        '$map' => [
                            'input' => '$task',
                            'as' => 't',
                            'in' => [
                                'title' => '$$t.title',
                                'id' => ['$toString' => '$$t._id']
                            ]
                        ]
                    ],
                    'user.name' => 1,
                    'user.last_name' => 1,
                    'user.profile_photo' => 1,
                ]]
            ];

            if ($limit !== -1) {
                $pipeline[] = ['$skip' => $skip];
                $pipeline[] = ['$limit' => $limit];
            }

            return $collection->aggregate($pipeline);
        });
        
        // Total count without pagination
        $totalCount = Timesheet::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],
                ['$unwind' => '$dates'],
                ['$unwind' => '$dates.time_log'],

                ['$count' => 'total']
            ]);
        })->first()['total'] ?? 0;

        return response()->json([
            'data' => $aggregation,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'total_pages' => $limit === -1 ? 1 : ceil($totalCount / $limit),
            ]
        ]);
    }

    // Get all timesheets for an employee
    public function index(Request $request)
    {
        $matchStage = [];

        // Pagination setup
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);
        $skip = ($page - 1) * $limit;

        // Role check
        if ($request->user->role->slug === 'employee' || $request->user->role->slug === 'qa') {
            $matchStage['employee_id'] = $request->user->id;
        }

        if ($request->user->role && $request->user->role->name === 'Administrator') {
            $matchStage['status'] = 'running';
        }

        // Basic filters
        foreach (['employee_id', 'project_id'] as $field) {
            if ($request->filled($field)) {
                $matchStage[$field] = $request->$field;
            }
        }

        // Exact date filter
        if ($request->filled('date')) {
            $matchStage['dates.date'] = $request->date;
        }

        // Date range filter
        if ($request->has(['start_date', 'end_date'])) {
            $matchStage['dates.date'] = [
                '$gte' => $request->start_date,
                '$lte' => $request->end_date,
            ];
        }

        // Task name search
        if ($request->filled('task_name')) {
            $tasks = Tasks::where('title', 'like', "%{$request->task_name}%")->get();
            $taskIds = Tasks::where('title', 'like', "%{$request->task_name}%")
                ->pluck('id')
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

        $aggregation = Timesheet::raw(function ($collection) use ($matchStage, $limit, $skip) {
            $pipeline = [
                ['$match' => $matchStage],
                ['$unwind' => '$dates'],
                ['$unwind' => '$dates.time_log'],

                // Pre-calculate time duration
                ['$addFields' => [
                    'start_time' => [
                        '$toDate' => [
                            '$concat' => ['$dates.date', 'T', '$dates.time_log.start_time', ':00']
                        ]
                    ],
                    'end_time' => [
                        '$toDate' => [
                            '$concat' => ['$dates.date', 'T', '$dates.time_log.end_time', ':00']
                        ]
                    ]
                ]],
                ['$addFields' => [
                    'duration_minutes' => [
                        '$divide' => [
                            ['$subtract' => ['$end_time', '$start_time']],
                            60000
                        ]
                    ]
                ]],

                // Group and summarize
                ['$group' => [
                    '_id' => '$_id',
                    'employee_id' => ['$first' => '$employee_id'],
                    'work_description' => ['$first' => '$work_description'],
                    'status' => ['$first' => '$status'],
                    'total_time_spent_minutes' => ['$sum' => '$duration_minutes'],
                    'created_at' => ['$first' => '$created_at'],
                    'task_id' => ['$first' => '$task_id'],
                    'project_id' => ['$first' => '$project_id'],
                    'task_type' => ['$first' => '$task_type'],
                    'dates' => ['$push' => '$dates']
                ]],

                // Join data (using simpler joins)
                ['$addFields' => [
                    'task_id' => ['$toObjectId' => '$task_id'],
                    'project_id' => ['$toObjectId' => '$project_id'],
                    'employee_id' => ['$toObjectId' => '$employee_id'],
                ]],

                ['$lookup' => [
                    'from' => 'tasks',
                    'localField' => 'task_id',
                    'foreignField' => '_id',
                    'as' => 'task'
                ]],
                
                [
                    '$addFields' => [
                        'task.task_type' => '$task_type'
                    ]
                ],
                ['$lookup' => [
                    'from' => 'projects',
                    'localField' => 'project_id',
                    'foreignField' => '_id',
                    'as' => 'project'
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'employee_id',
                    'foreignField' => '_id',
                    'as' => 'user'
                ]],


                // Format time string
                ['$addFields' => [
                    'total_hours' => ['$floor' => ['$divide' => ['$total_time_spent_minutes', 60]]],
                    'total_minutes' => ['$mod' => ['$total_time_spent_minutes', 60]],
                ]],
                ['$addFields' => [
                    'total_time_spent' => [
                        '$concat' => [
                            ['$toString' => '$total_hours'],
                            ':',
                            ['$cond' => [
                                'if' => ['$lt' => ['$total_minutes', 10]],
                                'then' => ['$concat' => ['0', ['$toString' => '$total_minutes']]],
                                'else' => ['$toString' => '$total_minutes']
                            ]]
                        ]
                    ]
                ]],
                ['$sort' => ['created_at' => -1]],
                ['$project' => [
                    '_id' => 1,
                    'employee_id' => 1,
                    'dates' => 1,
                    'total_time_spent' => 1,
                    'work_description' => 1,
                    'status' => 1,
                    'project' => 1,
                    'task_type' => 1,
                    'task' => 1,
                    'user' => 1,
                ]]
            ];

            if ($limit !== -1) {
                $pipeline[] = ['$skip' => $skip];
                $pipeline[] = ['$limit' => $limit];
            }

            return $collection->aggregate($pipeline);
        });
        
        // Total count without pagination
        $totalCount = Timesheet::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],
                ['$unwind' => '$dates'],
                ['$unwind' => '$dates.time_log'],
                ['$group' => ['_id' => '$_id']],
                ['$count' => 'total']
            ]);
        })->first()['total'] ?? 0;

        return response()->json([
            'data' => $aggregation,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'total_pages' => $limit === -1 ? 1 : ceil($totalCount / $limit),
            ]
        ]);
    }

    // Get all timesheets for an employee
    public function myTimesheet(Request $request)
    {
        $matchStage = [];

        if ($request->user->role->name == 'QA') {
            $matchStage['status'] = ['$nin' => ['completed', 'QA Failed']];
        }

        if ($request->user->role->name == 'Employee') {
            $matchStage['status'] = ['$nin' => ['completed', 'Ready For QA']];
        }   

        // Filter by Employee Role
        // if ($request->user->role->slug === 'employee') {
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
                    ],
                    'as' => 'task'
                ]],
                [
                    '$addFields' => [
                        'task.task_type' => '$task_type'
                    ]
                ],
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
                    'updated_at' => ['$first' => '$updated_at'],
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
                    'updated_at' => 1,
                ]]
            ]);
        });

        return response()->json($timesheets, 200);
    }

    //get resource occupancy for users that are working on tasks for today date
    public function resourceOccupancy(Request $request)
    {
        $todayDate = Carbon::now()->toDateString();
        $match = [
            'dates.date' => $todayDate,
        ];

        // Add employee filter if provided
        if ($request->filled('employee_id')) {
            $match['employee_id'] = $request->employee_id;
        }

        // Add project filter if provided
        if ($request->filled('project_id')) {
            $match['project_id'] = $request->project_id;
        }

        // Add task filter if provided
        if ($request->filled('task_id')) {
            $match['task_id'] = $request->task_id;
        }
        $pipeline = [
            ['$match' => $match],
            ['$unwind' => '$dates'],
            ['$match' => [
                'dates.date' => $todayDate,
            ]],
            ['$unwind' => '$dates.time_log'],

            // Compute start/end timestamps
            ['$addFields' => [
                'start_time_full' => [
                    '$concat' => ['$dates.date', 'T', '$dates.time_log.start_time', ':00']
                ],
                'end_time_full' => [
                    '$concat' => ['$dates.date', 'T', '$dates.time_log.end_time', ':00']
                ]
            ]],
            ['$addFields' => [
                'start_time_date' => [
                    '$dateFromString' => [
                        'dateString' => '$start_time_full',
                    ]
                ],
                'end_time_date' => [
                    '$dateFromString' => [
                        'dateString' => '$end_time_full',
                    ]
                ]
            ]],
            ['$addFields' => [
                'minutes' => [
                    '$divide' => [
                        ['$subtract' => ['$end_time_date', '$start_time_date']],
                        60000
                    ]
                ]
            ]],

            // Type conversions for lookups
            ['$addFields' => [
                'task_oid' => ['$toObjectId' => '$task_id'],
                'project_oid' => ['$toObjectId' => '$project_id'],
                'employee_oid' => ['$toObjectId' => '$employee_id'],
            ]],

            // Project join
            ['$lookup' => [
                'from' => 'projects',
                'localField' => 'project_oid',
                'foreignField' => '_id',
                'as' => 'project'
            ]],
            ['$addFields' => [
                'project_name' => [
                    '$arrayElemAt' => ['$project.project_name', 0]
                ]
            ]],

            // Task join
            ['$lookup' => [
                'from' => 'tasks',
                'localField' => 'task_oid',
                'foreignField' => '_id',
                'as' => 'task'
            ]],
            ['$addFields' => [
                'task_name' => [
                    '$arrayElemAt' => ['$task.title', 0]
                ],
                'task_type' => [
                    '$arrayElemAt' => ['$task.task_type', 0]
                ]
            ]],

            // User join
            ['$lookup' => [
                'from' => 'users',
                'localField' => 'employee_oid',
                'foreignField' => '_id',
                'as' => 'user'
            ]],
            ['$addFields' => [
                'employee_name' => [
                    '$arrayElemAt' => ['$user.name', 0]
                ]
            ]],

            // Final projection
            ['$project' => [
                '_id' => 1,
                'project_id' => '$project_id',
                'project_name' => 1,
                'task_id' => '$task_id',
                'task_name' => 1,
                'task_type' => 1,
                'employee_id' => '$employee_id',
                'employee_name' => 1,
                'dates' => [
                    'date' => '$dates.date',
                    'time_log' => [
                        [
                            'start_time' => '$dates.time_log.start_time',
                            'end_time' => '$dates.time_log.end_time'
                        ]
                    ]
                ],
                'minutes' => 1,
                'work_description' => 1,
                'status' => 1,
                'updated_at' => 1,
                'created_at' => 1
            ]],
            ['$sort' => ['updated_at' => -1]],
        ];

        $result = Timesheet::raw(function ($collection) use ($pipeline) {
            return $collection->aggregate($pipeline);
        });

        return response()->json($result, 200);
    }

    public function resourceAvilible(Request $request)
    {
        
        $today = Carbon::today()->format('Y-m-d');

        //  Get Administrator role ID
        $roleId = Role::where('name', 'Administrator')->value('id');

        //  Employees who logged in today
        $loggedInEmployeeIds = LoginSession::where('created_at', '>=', Carbon::today())
            ->where('created_at', '<', Carbon::tomorrow())
            ->groupBy('employee_id')
            ->pluck('employee_id')
            ->toArray();
        
        //  Employees who already have timesheet today (BUSY)
        $busyEmployeeIds = Timesheet::where('dates.date', $today)
            ->where('task_type', '!=', 'R&D')
            ->where('status', '!=', 'paused')
            ->where('status', '!=', 'Ready For QA')
            ->where('status', '!=', 'completed')
            ->groupBy('employee_id')
            ->pluck('employee_id')
            ->toArray();

        $userlist = User::raw(function ($collection) use ($roleId, $loggedInEmployeeIds, $busyEmployeeIds, $today) {
            $loggedInOids = array_map(fn($id) => new ObjectId($id), array_values($loggedInEmployeeIds));
            $busyOids = array_map(fn($id) => new ObjectId($id), array_values($busyEmployeeIds));

            return $collection->aggregate([
                ['$match' => [
                    'role_id' => ['$ne' => $roleId],
                    'is_logout' => ['$ne' => true],
                    '_id' => [
                        '$in' => $loggedInOids,
                        '$nin' => $busyOids
                    ]
                ]],

                ['$lookup' => [
                    'from' => 'timesheets',
                    'let' => ['userId' => ['$toString' => '$_id']],
                    'pipeline' => [
                        ['$match' => [
                            '$expr' => ['$and' => [
                                ['$eq' => ['$employee_id', '$$userId']],
                                ['$eq' => ['$status', 'running']],
                                ['$in' => [$today, '$dates.date']]
                            ]]
                        ]],
                        ['$limit' => 1]
                    ],
                    'as' => 'timesheet_doc'
                ]],
                ['$unwind' => ['path' => '$timesheet_doc', 'preserveNullAndEmptyArrays' => true]],
                ['$addFields' => [
                    'today_time_log' => [
                        '$let' => [
                            'vars' => [
                                'matched_date' => [
                                    '$arrayElemAt' => [
                                        ['$filter' => [
                                            'input' => ['$ifNull' => ['$timesheet_doc.dates', []]],
                                            'as' => 'd',
                                            'cond' => ['$eq' => ['$$d.date', $today]]
                                        ]],
                                        0
                                    ]
                                ]
                            ],
                            'in' => '$$matched_date.time_log'
                        ]
                    ]
                ]],
                ['$addFields' => [
                    'today_total_minutes' => [
                        '$reduce' => [
                            'input' => ['$ifNull' => ['$today_time_log', []]],
                            'initialValue' => 0,
                            'in' => [
                                '$add' => [
                                    '$$value',
                                    [
                                        '$divide' => [
                                            ['$subtract' => [
                                                ['$dateFromString' => ['dateString' => ['$concat' => [$today, 'T', '$$this.end_time', ':00']]]],
                                                ['$dateFromString' => ['dateString' => ['$concat' => [$today, 'T', '$$this.start_time', ':00']]]]
                                            ]],
                                            60000
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]],
                ['$lookup' => [
                    'from' => 'tasks',
                    'let' => ['taskId' => ['$toObjectId' => '$timesheet_doc.task_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskId']]]]
                    ],
                    'as' => 'task_doc'
                ]],
                ['$unwind' => ['path' => '$task_doc', 'preserveNullAndEmptyArrays' => true]],

                 ['$project' => [
                     '_id' => 1,
                     'name' => 1,
                     'last_name' => 1,
                     'profile_photo' => 1,
                     'current_task' => [
                         '$cond' => [
                             'if' => ['$ifNull' => ['$task_doc', false]],
                             'then' => [
                                'title' => '$task_doc.title',
                                'working_description' => '$timesheet_doc.work_description',
                                'total_time_spent' => [
                                    '$concat' => [
                                        ['$toString' => ['$floor' => ['$divide' => ['$today_total_minutes', 60]]]],
                                        ':',
                                        ['$cond' => [
                                            'if' => ['$lt' => [['$mod' => ['$today_total_minutes', 60]], 10]],
                                            'then' => ['$concat' => ['0', ['$toString' => ['$mod' => ['$today_total_minutes', 60]]]]],
                                            'else' => ['$toString' => ['$mod' => ['$today_total_minutes', 60]]]
                                        ]]
                                    ]
                                ],
                            ],
                             'else' => null
                         ]
                     ]
                 ]]
            ]);
        });
        
        return response()->json([
            'data' => $userlist
        ]);

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
            'task_type' => 'required|string',
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
            'end_time' => now()->format('H:i'),
        ];

        $datesArray = [[
            'date' => $currentDate,
            'time_log' => [$timeLogEntry],
        ]];

        if ($timesheet) {

            if ($timesheet->status === 'completed') {
                return response()->json([
                    'message' => 'Task already completed.'
                ], 400);
            }

            if($request->user->role->name == 'Employee' && $timesheet->status == 'Ready For QA'){

                $task = Tasks::with('status')
            ->where('_id', $request->task_id)
            ->first();  
           
                if($task->status->name == 'QA Failed'){

                    $dates = $timesheet->dates ?? [];

                $dateFound = false;

            foreach ($dates as &$dateEntry) {
                    if ($dateEntry['date'] === $currentDate) {
                        $dateEntry['time_log'][] = $timeLogEntry;
                        $dateFound = true;
                        break;
                    }
            }

                if (!$dateFound) {
                    $dates[] = [
                        'date' => $currentDate,
                        'time_log' => [$timeLogEntry],
                    ];
                }

                $timesheet->update([
                    'dates'  => $dates,
                    'status' => 'running',
                ]);

                }else{
                    return response()->json([
                        'message' => 'Task already assigned to QA.'
                    ], 400);
                }
            }

            if($request->user->role->name == 'QA'){

                $dates = $timesheet->dates ?? [];

                $dateFound = false;

            foreach ($dates as &$dateEntry) {
                    if ($dateEntry['date'] === $currentDate) {
                        $dateEntry['time_log'][] = $timeLogEntry;
                        $dateFound = true;
                        break;
                    }
            }

                if (!$dateFound) {
                    $dates[] = [
                        'date' => $currentDate,
                        'time_log' => [$timeLogEntry],
                    ];
                }

                $timesheet->update([
                    'dates'  => $dates,
                    'status' => 'running',
                ]);

            }

        }else{

             // Create a new timesheet entry
            $timesheet = Timesheet::create([
                'project_id' => $request->project_id,
                'task_id' => $request->task_id,
                'task_type' => $request->task_type,
                'employee_id' => $userId,
                'dates' => [
                    [
                        'date' => $currentDate,
                        'time_log' => [
                            [
                                'start_time' => now()->format('H:i'),
                                'end_time' => now()->format('H:i'),
                            ]
                        ],
                    ]
                ], // Store as a plain PHP array
                'work_description' => $request->work_description,
                'status' => 'running',
            ]);
            
        }

        if($request->user->role->name == 'QA'){

            $task = Tasks::with('status')
            ->where('_id', $request->task_id)
            ->first();

            if (!$task) {
                return response()->json(['message' => 'Task not found'], 404);
            }

           if($task->status->name == 'Ready For QA' || $task->status->name == 'on hold' || $task->status->name == 'In Progress (QA)' || $task->status->name == 'QA Failed'){

            $taskStatusId = TaskStatus::where('name', 'In Progress (QA)')
            ->value('_id');

            // update task status to 'Completed'
            $task->update([
                'status_id' => $taskStatusId
            ]);
           }         
        }
        else{
            $statusId = TaskStatus::where('name', 'In Progress (Dev)')->value('_id');

            Tasks::where('_id', $request->task_id)->update([
                'status_id' => $statusId
            ]);
        }

        // Pause any other running timesheets for the user
        Timesheet::where('employee_id', $userId)
            ->where('_id', '!=', $timesheet->_id)
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'Ready For QA')
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

    public function manualEntry(Request $request)
    {
        $userId = $request->user->id;
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,_id',
            'task_id' => 'required|exists:tasks,_id',
            'task_type' => 'required|string',
            'work_description' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create a new timesheet entry
        $timesheet = Timesheet::create([
            'project_id' => $request->project_id,
            'task_id' => $request->task_id,
            'task_type' => $request->task_type,
            'employee_id' => $userId,
            'dates' => [
                [
                    'date' => $request->date,
                    'time_log' => [
                        [
                            'start_time' => $request->start_time,
                            'end_time' => $request->end_time,
                        ]
                    ],
                ]
            ], // Store as a plain PHP array
            'work_description' => $request->work_description,
            'status' => 'completed',
        ]);

        return response()->json($timesheet, 201);
    }

    public function stopTask(Request $request, $id)
    {
        $timesheet = Timesheet::where('id', $id)->first();

        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $timesheet->status = 'paused';
        $timesheet->save();
        $userId = $request->user->id; // Get authenticated user ID
        if ($timesheet->task_type == 'Helping Hand') {
            $task_id = $timesheet->task_id;
            $HelpingHand = HelpingHand::where('task_id', $task_id)->where('to_id', $userId)->where('status', 'accepted')->first();
            if ($HelpingHand) {
                $HelpingHand->status = 'completed';
                $HelpingHand->save();
                HelpingHandController::sendPushNotification($HelpingHand);
            }
        }
        $this->userBreakLogStart($userId);

        if($request->user->role->name == 'QA'){

            $task = Tasks::with('status')
            ->where('_id', $timesheet->task_id)
            ->first();

            if (!$task) {
                return response()->json(['message' => 'Task not found'], 404);
            }

           if($task->status->name == 'Ready For QA' || $task->status->name == 'In Progress (QA)'){

            $taskStatusId = TaskStatus::where('name', 'On Hold (QA)')
            ->value('_id');

            $task->update([
                'status_id' => $taskStatusId
            ]);
           }         

        }else{

        $statusId = TaskStatus::where('name', 'On Hold')->value('_id');

        Tasks::where('_id', $timesheet->task_id)->update([
            'status_id' => $statusId
        ]);

        }

        $formattedTotal = $this->calculate_total_time_spent_by_task($timesheet);
        $timesheetArray = $timesheet->toArray();
        $timesheetArray['total_spent_time'] = $formattedTotal;
        return response()->json($timesheetArray);
    }

    public function completeTask(Request $request, $id)
    {
        $timesheet = Timesheet::where('id', $id)->first();

        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }

         $task = Tasks::where('_id', $timesheet->task_id)->first();

         if($request->user->role->name == 'QA'){

            $timesheet->status = 'completed';
            $timesheet->save();

            Timesheet::where('task_id', $timesheet->task_id)
            ->update(['status' => 'completed']);

            $assigneeId = $task->assignees[0] ?? null;

            if ($assigneeId)     {
                $user = User::find($assigneeId);

                if ($user) {
                    $user->notify(new PushNotification('Task Completed', 'Your task is completed'));
                }
            }

            $parentTasks = Tasks::where('parent_task_id', $timesheet->task_id)->get();

            $statusId = TaskStatus::where('name', 'Completed')->value('_id');

            foreach ($parentTasks as $parentTask) {

                // Update parent task timesheet
                Timesheet::where('task_id', $parentTask->_id)
                    ->update(['status' => 'completed']);

                // Update parent task status
                Tasks::where('_id', $parentTask->_id)
                    ->update(['status_id' => $statusId]);
            }

         }else{

            if (empty($task?->qa_id)) {
            $timesheet->status = 'completed';
            $timesheet->save();

            Timesheet::where('task_id', $timesheet->task_id)
            ->update(['status' => 'completed']);

            }else{

            $timesheet->status = 'Ready For QA';
            $timesheet->save();

             $user = User::find($task->qa_id);
            $user->notify(new PushNotification('Task Ready For QA', 'Your task is ready for QA'));

            }
            
         }

        $userId = $request->user->id; // Get authenticated user ID

        if($request->user->role->name == 'QA'){

            $statusId = TaskStatus::where('name', 'Completed')->value('_id');

            Tasks::where('_id', $timesheet->task_id)->update([
                'status_id' => $statusId

            ]); 

        }else{

        if (empty($task?->qa_id)) {

           $statusId = TaskStatus::where('name', 'Completed')->value('_id');
        
        Tasks::where('_id', $timesheet->task_id)->update([
            'status_id' => $statusId
        ]); 

         }else{
             $statusId = TaskStatus::where('name', 'Ready For QA')->value('_id');
        
        Tasks::where('_id', $timesheet->task_id)->update([
            'status_id' => $statusId
        ]); 
         }

        }

        if ($timesheet->task_type == 'Helping Hand') {
            $task_id = $timesheet->task_id;
            $HelpingHand = HelpingHand::where('task_id', $task_id)->where('to_id', $userId)->where('status', 'accepted')->first();
            if ($HelpingHand) {
                $HelpingHand->status = 'Ready For QA';
                $HelpingHand->save();
                HelpingHandController::sendPushNotification($HelpingHand);
            }
        }
        $this->userBreakLogStart($userId);

        $formattedTotal = $this->calculate_total_time_spent_by_task($timesheet);
        $timesheetArray = $timesheet->toArray();
        $timesheetArray['total_spent_time'] = $formattedTotal;

        return response()->json($timesheetArray);
    }
    
    public function calculate_total_time_spent_by_task($timesheet){
        // ➕ Calculate total time spent
        $totalMinutes = 0;

        foreach ($timesheet->dates as $dateEntry) {
            foreach ($dateEntry['time_log'] as $log) {
                if (!empty($log['start_time']) && !empty($log['end_time'])) {
                    $start = Carbon::createFromFormat('H:i', $log['start_time']);
                    $end = Carbon::createFromFormat('H:i', $log['end_time']);
                    $diff = $end->diffInMinutes($start);
                    $totalMinutes += $diff;
                }
            }
        }

        $interval = CarbonInterval::minutes($totalMinutes)->cascade();

        $formattedTotal = sprintf('%02d:%02d', $interval->hours + ($interval->days * 24), $interval->minutes);

        return $formattedTotal;
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
                if ($diffMinutes >= 1) {
                    // If more than 5 minutes have passed, create a new time log entry.
                    $timeLogs[] = [
                        'start_time' => $now->format('H:i'),
                        'end_time'   => Carbon::now()->format('H:i'),
                    ];
                } else {
                    // Otherwise, update the end_time of the last log.
                    $lastIndex = count($timeLogs) - 1;
                    $timeLogs[$lastIndex]['end_time'] = Carbon::now()->format('H:i');
                }
            } else {
                // If there are no time logs, create one.
                $timeLogs[] = [
                    'start_time' => $now->format('H:i'),
                    'end_time'   => Carbon::now()->format('H:i'),
                ];
            }
            $dates[$existingDateKey]['time_log'] = $timeLogs;
        } else {
            // If today's date does not exist, create a new entry with a new time log.
            $dates[] = [
                'date'     => $currentDate,
                'time_log' => [[
                    'start_time' => $now->format('H:i'),
                    'end_time'   => Carbon::now()->format('H:i'),
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
            ->where('status', 'running')
            ->update(['status' => 'paused']);

            if($request->user->role->name == 'QA'){

            $task = Tasks::with('status')
            ->where('_id', $timesheet->task_id)
            ->first();

            if (!$task) {
                return response()->json(['message' => 'Task not found'], 404);
            }

           if($task->status->name == 'Ready For QA' || $task->status->name == 'On Hold (QA)'){

            $taskStatusId = TaskStatus::where('name', 'In Progress (QA)')
            ->value('_id');

            // update task status to 'Completed'
            $task->update([
                'status_id' => $taskStatusId
            ]);   
           }         
        }else{

            $statusId = TaskStatus::where('name', 'In Progress (Dev)')->value('_id');

            Tasks::where('_id', $timesheet->task_id)->update([
                        'status_id' => $statusId
                    ]);
        }
        
        // Stop break log if active.
        $this->userBreakLogStop($userId);

        $formattedTotal = $this->calculate_total_time_spent_by_task($timesheet);
        $timesheetArray = $timesheet->toArray();
        $timesheetArray['total_spent_time'] = $formattedTotal;
        return response()->json($timesheetArray);
    }

    public function qaFailedTask(Request $request, $id)
    {
        $timesheet = Timesheet::where('id', $id)->first();

        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $timesheet->status = 'QA Failed';
        $timesheet->save();

        $userId = $request->user->id;

        if ($timesheet->task_type == 'Helping Hand') {
            $task_id = $timesheet->task_id;
            $HelpingHand = HelpingHand::where('task_id', $task_id)->where('to_id', $userId)->where('status', 'accepted')->first();
            if ($HelpingHand) {
                $HelpingHand->status = 'completed';
                $HelpingHand->save();
                HelpingHandController::sendPushNotification($HelpingHand);
            }
        }

        $this->userBreakLogStart($userId);

        $statusId = TaskStatus::where('name', 'QA Failed')->value('_id');

        Tasks::where('_id', $timesheet->task_id)->update([
            'status_id' => $statusId
        ]);

        $task = Tasks::where('_id', $timesheet->task_id)->first();

        $assigneeId = $task->assignees[0] ?? null;

        if ($assigneeId) {
            $user = User::find($assigneeId);

            if ($user) {
              $user->notify(new PushNotification('Task QA Failed', 'Your task is failed in QA'));
            }
        }

        $formattedTotal = $this->calculate_total_time_spent_by_task($timesheet);
        $timesheetArray = $timesheet->toArray();
        $timesheetArray['total_spent_time'] = $formattedTotal;

        return response()->json($timesheetArray);
    }
    
    // public function completeTask(Request $request, $id)
    // {
    //     $userId = $request->user->id;
    //     $timesheet = Timesheet::where('id', $id)->first();
    //     if (!$timesheet) {
    //         return response()->json(['message' => 'Timesheet not found'], 404);
    //     }
    //     $timesheet->status = 'completed';
    //     $this->userBreakLogStart($userId);
    //     $timesheet->save();
    //     return response()->json($timesheet);
    // }

    public function startBreak(Request $request)
    {
        $userId = $request->user->id;
        $this->userBreakLogStart($userId);
        return response()->json(['message' => 'Break Start'], 200);
    }

    public function stopBreak(Request $request)
    {
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
            $existingEntry = collect($breakLog)->firstWhere('start_time', Carbon::now()->format('H:i'));

            if ($existingEntry) {
                // If an entry with the same start_time exists, do nothing
                // return;
                // If no entry exists, execute the current code
                $session->break = true;
                // $endTime = Carbon::now()->format('H:i');
                $breakLog[] = [
                    'start_time' => Carbon::now()->format('H:i'),
                    'end_time' => Carbon::now()->format('H:i'),
                ];
                $session->break_log = $breakLog;
                $session->save();
            } else {
                // If no entry exists, execute the current code
                $session->break = true;
                // $endTime = Carbon::now()->format('H:i');
                $breakLog[] = [
                    'start_time' => Carbon::now()->format('H:i'),
                    'end_time' => Carbon::now()->format('H:i'),
                ];
                $session->break_log = $breakLog;
                $session->save();
            }
        }
    }
    
    public function userBreakLogStop($userId)
    {
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
            'task_type' => 'required|string',
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
            'task_type' => $request->task_type,
            // 'start_time' => $request->start_time,
            // 'end_time' => $request->end_time,
            'work_description' => $request->work_description,
        ]);
        return response()->json($timesheet, 200);
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

    public function spentTimeByRole(Request $request)
    {
        $task_id = $request->task_id;

        // Fetch child task IDs to identify "Bug" tasks
        $childTaskIds = Tasks::where('parent_task_id', $task_id)
            ->get(['_id'])
            ->pluck('_id')
            ->map(function ($id) {
                return (string) $id;
            })
            ->toArray();

        // All tasks to search for (Parent + Children)
        $allTaskIds = array_merge([$task_id], $childTaskIds);

        $data = Timesheet::raw(function ($collection) use ($allTaskIds, $task_id) {
            return $collection->aggregate([
                ['$match' => ['task_id' => ['$in' => $allTaskIds]]],
                ['$unwind' => '$dates'],
                ['$unwind' => '$dates.time_log'],
                [
                    '$addFields' => [
                        'duration' => [
                            '$subtract' => [
                                ['$add' => [
                                    ['$multiply' => [['$toInt' => ['$arrayElemAt' => [['$split' => ['$dates.time_log.end_time', ':']], 0]]], 60]],
                                    ['$toInt' => ['$arrayElemAt' => [['$split' => ['$dates.time_log.end_time', ':']], 1]]]
                                ]],
                                ['$add' => [
                                    ['$multiply' => [['$toInt' => ['$arrayElemAt' => [['$split' => ['$dates.time_log.start_time', ':']], 0]]], 60]],
                                    ['$toInt' => ['$arrayElemAt' => [['$split' => ['$dates.time_log.start_time', ':']], 1]]]
                                ]]
                            ]
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$employee_id',
                        'dev_minutes' => [
                            '$sum' => [
                                '$cond' => [
                                    'if' => ['$eq' => ['$task_id', $task_id]],
                                    'then' => '$duration',
                                    'else' => 0
                                ]
                            ]
                        ],
                        'bug_minutes' => [
                            '$sum' => [
                                '$cond' => [
                                    'if' => ['$ne' => ['$task_id', $task_id]], // Any task not equal to parent is considered bug/child
                                    'then' => '$duration',
                                    'else' => 0
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
        });

        $employeeIds = collect($data)->pluck('_id')->toArray();
        $users = User::whereIn('_id', $employeeIds)->get()->keyBy('_id');

        $formatted = collect($data)->map(function ($item) use ($users, $childTaskIds) {
            $user = $users[(string)$item->_id] ?? null;

            $formatTime = function ($totalMinutes) {
                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                return sprintf('%02d:%02d', $hours, $minutes);
            };

            $response = [
                'id' => $item->_id,
                'name' => $user->name ?? '',
                'last_name' => $user->last_name ?? '',
                'profile_photo' => $user->profile_photo ?? null,
                'role' => $user->role->name ?? null,
            ];

            // Check if user is QA
            if ($user && $user->role && ($user->role->name === 'QA' || $user->role->slug === 'qa')) {
                $totalMinutes = $item->dev_minutes + $item->bug_minutes;
                $response['test_spent_time'] = $formatTime($totalMinutes);
            } else {
                $response['dev_spent_time'] = $formatTime($item->dev_minutes);
                if (!empty($childTaskIds)) {
                    $response['bug_spent_time'] = $formatTime($item->bug_minutes);
                }
            }

            return $response;
        });

        return response()->json($formatted);
    }

}
