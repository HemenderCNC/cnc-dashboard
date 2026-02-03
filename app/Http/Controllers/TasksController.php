<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
use App\Models\TaskStatus;
use App\Models\Milestones;
use App\Models\Project;
use App\Models\User;
use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use Carbon\Carbon;

class TasksController extends Controller
{
    public function index(Request $request)
    {
        $matchStage = (object)[]; // Ensure it's an object, not an empty array
        // Pagination setup
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);
        $skip = ($page - 1) * $limit;

        if ($request->user->role->slug === 'employee' || $request->user->role->slug === 'qa') {
            $matchStage->assignees = $request->user->id;
        } else if ($request->has('employee_id')) {
            $matchStage->assignees = $request->employee_id;
        }

        if ($request->user->role && $request->user->role->name === 'QA') {
            $qaStatuses = TaskStatus::whereIn('name', [
                'Ready For QA',
                'In Progress (QA)',
                'On Hold (QA)',
                'QA Failed'
            ])->get();

            $qaStatusIds = $qaStatuses->pluck('_id')->map(fn($id) => (string)$id)->toArray();

            if (!empty($qaStatusIds)) {
                if (isset($matchStage->assignees)) {
                    $matchStage->{'$or'} = [
                        ['assignees' => $matchStage->assignees],
                        [
                            'qa_id' => $request->user->id,
                            'status_id' => ['$in' => $qaStatusIds]
                        ]
                    ];
                    unset($matchStage->assignees);
                } else {
                    $matchStage->qa_id = $request->user->id;
                    $matchStage->status_id = ['$in' => $qaStatusIds];
                }
            }
        }
        
        // Filter by project name (partial match)
        if ($request->has('title')) {
            $matchStage->title = ['$regex' => $request->title, '$options' => 'i'];
        }

        // Filter by client ID
        if ($request->has('project_id')) {
            $matchStage->project_id = $request->project_id;
        }

        // Filter by project industry
        if ($request->has('milestone_name')) {
            // Get all matching milestones first
            $milestoneIds = Milestones::whereRaw([
                'name' => ['$regex' => $request->milestone_name, '$options' => 'i']
            ])->pluck('id')->map(function ($id) {
                return (string) $id;
            })->toArray();

            // If we found any milestones, match their IDs
            if (count($milestoneIds) > 0) {
                $matchStage->milestone_id = ['$in' => $milestoneIds];
            }
        }
        if ($request->has('milestone_id')) {
            $matchStage->milestone_id = $request->milestone_id;
        }

        // Filter by project status
        if ($request->has('status_id')) {
            $matchStage->status_id = $request->status_id;
        } else {
            // Exclude "Complete" tasks (case-insensitive check)
            $completedStatus = TaskStatus::whereRaw([
                'name' => ['$regex' => '^complete$', '$options' => 'i']
            ])->first();

            if ($completedStatus) {
                $matchStage->status_id = ['$ne' => (string) $completedStatus->_id];
            }
        }

        if ($request->has('task_type_id')) {
            $matchStage->task_type_id = $request->task_type_id;
        }
        if ($request->has('priority')) {
            $matchStage->priority = $request->priority;
        }
        if ($request->has('task_id')) {
            $matchStage->task_id = $request->task_id;
        }
        if ($request->has('owner_id')) {
            $matchStage->owner_id = $request->owner_id;
        }

        // Filter by platforms (array match)
        if ($request->has('assignee_id')) {
            $matchStage->assignees = ['$in' => array_map('strval', (array) $request->assignee_id)];
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $startDate = Carbon::parse($request->start_date)->startOfDay()->toIso8601String();
            $endDate = Carbon::parse($request->end_date)->endOfDay()->toIso8601String();

            $matchStage->start_date  = [
                '$gte' => $startDate,
                '$lte' => $endDate
            ];
        }

        // Exclude "Complete" tasks (case-insensitive check)
        // $completedStatus = TaskStatus::whereRaw([
        //     'name' => ['$regex' => '^complete$', '$options' => 'i']
        // ])->first();

        // if ($completedStatus) {
        //     $matchStage->status_id = ['$ne' => (string) $completedStatus->_id];
        // }
        // Exclude "Complete" tasks END

        // Ensure matchStage is not empty
        if (empty((array) $matchStage)) {
            $matchStage = (object)[]; // Empty object for MongoDB
        }
        $sortStage = ['$sort' => ['created_at' => -1]]; // Default sorting by created_at (Descending)
        $matchDueDate = null;
        if ($request->has('sort') && $request->sort === 'due_date') {
            $todayTimestamp = Carbon::today()->toIso8601String(); // Convert to milliseconds
            $matchDueDate = ['$match' => ['due_date' => ['$gte' => $todayTimestamp]]];
            $sortStage = ['$sort' => ['due_date' => 1]];
        }

        // MongoDB Aggregation Pipeline
        $tasks = Tasks::raw(function ($collection) use ($matchStage, $sortStage, $matchDueDate, $skip, $limit) {
            $pipeline = [
                ['$match' => $matchStage],  // Apply Filters
            ];

            if ($matchDueDate) {
                $pipeline[] = $matchDueDate;
            }

            // Lookup operations
            $pipeline = array_merge($pipeline, [
                ['$lookup' => [
                    'from' => 'projects',
                    'let' => ['statusId' => ['$toObjectId' => '$project_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$statusId']]]]
                    ],
                    'as' => 'project'
                ]],
                ['$lookup' => [
                    'from' => 'milestones',
                    'let' => ['milestoneId' => ['$toObjectId' => '$milestone_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$milestoneId']]]]
                    ],
                    'as' => 'milestone'
                ]],
                [
                    '$lookup' => [
                        'from' => 'users',
                        'let' => [
                            'assigneeIds' => [
                                '$map' => [
                                    'input' => ['$ifNull' => ['$assignees', []]],
                                    'as' => 'id',
                                    'in' => ['$toObjectId' => '$$id'] // <--- THIS IS REQUIRED!
                                ]
                            ]
                        ],
                        'pipeline' => [
                            [
                                '$match' => [
                                    '$expr' => [
                                        '$in' => ['$_id', '$$assigneeIds']
                                    ]
                                ]
                            ],
                            [
                                '$project' => [
                                    '_id' => 1,
                                    'name' => 1,
                                    'last_name' => 1,
                                    'personal_email' => 1,
                                    'contact_number' => 1,
                                    'profile_photo' => 1
                                ]
                            ]
                        ],
                        'as' => 'assignees_data'
                    ]
                ],

                ['$lookup' => [
                    'from' => 'task_statuses',
                    'let' => ['taskStatusId' => ['$toObjectId' => '$status_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskStatusId']]]]
                    ],
                    'as' => 'task_status'
                ]],
                ['$lookup' => [
                    'from' => 'task_types',
                    'let' => ['taskTypeId' => ['$toObjectId' => '$task_type_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskTypeId']]]]
                    ],
                    'as' => 'task_type'
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'let' => ['ownerId' => ['$toObjectId' => '$owner_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$ownerId']]]]
                    ],
                    'as' => 'owner'
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'let' => ['createdBy' => ['$toObjectId' => '$created_by']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$createdBy']]]]
                    ],
                    'as' => 'created_bys'
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'let' => ['qaId' => ['$toObjectId' => '$qa_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$qaId']]]]
                    ],
                    'as' => 'qa'
                ]],
                ['$lookup' => [
                    'from' => 'tasks',
                    'let' => ['taskId' => ['$toString' => '$_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$parent_task_id', '$$taskId']]]],
                        ['$lookup' => [
                            'from' => 'projects',
                            'let' => ['statusId' => ['$toObjectId' => '$project_id']], // Convert to ObjectId
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$statusId']]]]
                            ],
                            'as' => 'project'
                        ]],
                        ['$lookup' => [
                            'from' => 'users',
                            'let' => ['ownerId' => ['$toObjectId' => '$owner_id']],
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$ownerId']]]]
                            ],
                            'as' => 'owner'
                        ]],
                        [
                            '$lookup' => [
                                'from' => 'users',
                                'let' => [
                                    'assigneeIds' => [
                                        '$map' => [
                                            'input' => ['$ifNull' => ['$assignees', []]],
                                            'as' => 'id',
                                            'in' => ['$toObjectId' => '$$id'] // <--- THIS IS REQUIRED!
                                        ]
                                    ]
                                ],

                                'pipeline' => [
                                    [
                                        '$match' => [
                                            '$expr' => [
                                                '$in' => ['$_id', '$$assigneeIds']
                                            ]
                                        ]
                                    ],
                                    [
                                        '$project' => [
                                            '_id' => 1,
                                            'name' => 1,
                                            'last_name' => 1,
                                            'personal_email' => 1,
                                            'contact_number' => 1,
                                            'profile_photo' => 1
                                        ]
                                    ]
                                ],
                                'as' => 'assignees_data'
                            ]
                        ],
                        ['$lookup' => [
                            'from' => 'task_statuses',
                            'let' => ['taskStatusId' => ['$toObjectId' => '$status_id']],
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskStatusId']]]]
                            ],
                            'as' => 'child_task_status'
                        ]],
                        ['$lookup' => [
                            'from' => 'task_types',
                            'let' => ['taskTypeId' => ['$toObjectId' => '$task_type_id']],
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskTypeId']]]]
                            ],
                            'as' => 'child_task_type'
                        ]],
                        ['$lookup' => [
                            'from' => 'milestones',
                            'let' => ['milestoneId' => ['$toObjectId' => '$milestone_id']],
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$milestoneId']]]]
                            ],
                            'as' => 'child_task_milestone'
                        ]],
                        ['$lookup' => [
                            'from' => 'users',
                            'let' => ['createdBy' => ['$toObjectId' => '$created_by']],
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$createdBy']]]],
                                ['$project' => [
                                    'name' => 1,
                                    'last_name' => 1,
                                    'profile_photo' => 1
                                ]]
                            ],
                            'as' => 'created_bys'
                        ]],
                        ['$lookup' => [
                            'from' => 'timesheets',
                            'let' => ['childTaskId' => '$_id'],
                            'pipeline' => [
                                [
                                    '$match' => [
                                        '$expr' => [
                                            '$eq' => [
                                                ['$toObjectId' => '$task_id'],
                                                '$$childTaskId'
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    '$unwind' => '$dates'
                                ],
                                [
                                    '$unwind' => '$dates.time_log'
                                ],
                                [
                                    '$addFields' => [
                                        'start' => [
                                            '$dateFromString' => [
                                                'dateString' => [
                                                    '$concat' => [
                                                        '$dates.date',
                                                        'T',
                                                        '$dates.time_log.start_time',
                                                        ':00'
                                                    ]
                                                ],
                                                'format' => '%Y-%m-%dT%H:%M:%S'
                                            ]
                                        ],
                                        'end' => [
                                            '$dateFromString' => [
                                                'dateString' => [
                                                    '$concat' => [
                                                        '$dates.date',
                                                        'T',
                                                        '$dates.time_log.end_time',
                                                        ':00'
                                                    ]
                                                ],
                                                'format' => '%Y-%m-%dT%H:%M:%S'
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    '$addFields' => [
                                        'durationInMinutes' => [
                                            '$divide' => [
                                                ['$subtract' => ['$end', '$start']],
                                                1000 * 60
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    '$group' => [
                                        '_id' => null,
                                        'total_minutes' => ['$sum' => '$durationInMinutes']
                                    ]
                                ],
                                [
                                    '$addFields' => [
                                        'total_hours' => [
                                            '$floor' => [
                                                '$divide' => ['$total_minutes', 60]
                                            ]
                                        ],
                                        'remaining_minutes' => [
                                            '$mod' => ['$total_minutes', 60]
                                        ]
                                    ]
                                ]
                            ],
                            'as' => 'child_timesheet_data'
                        ]],
                        [
                            '$addFields' => [
                                'total_hours' => [
                                    '$ifNull' => [['$arrayElemAt' => ['$child_timesheet_data.total_hours', 0]], 0]
                                ],
                                'total_minutes' => [
                                    '$ifNull' => [['$arrayElemAt' => ['$child_timesheet_data.remaining_minutes', 0]], 0]
                                ]
                            ]
                        ],
                        ['$project' => ['child_timesheet_data' => 0]]
                    ],
                    'as' => 'child_tasks'
                ]],
                [
                    '$lookup' => [
                        'from' => 'timesheets',
                        'let' => ['taskId' => '$_id'],
                        'pipeline' => [
                            [
                                '$match' => [
                                    '$expr' => [
                                        '$eq' => [
                                            ['$toObjectId' => '$task_id'],
                                            '$$taskId'
                                        ]
                                    ]
                                ]
                            ],
                            [
                                '$unwind' => '$dates'
                            ],
                            [
                                '$unwind' => '$dates.time_log'
                            ],
                            [
                                '$addFields' => [
                                    'start' => [
                                        '$dateFromString' => [
                                            'dateString' => [
                                                '$concat' => [
                                                    '$dates.date',
                                                    'T',
                                                    '$dates.time_log.start_time',
                                                    ':00'
                                                ]
                                            ],
                                            'format' => '%Y-%m-%dT%H:%M:%S'
                                        ]
                                    ],
                                    'end' => [
                                        '$dateFromString' => [
                                            'dateString' => [
                                                '$concat' => [
                                                    '$dates.date',
                                                    'T',
                                                    '$dates.time_log.end_time',
                                                    ':00'
                                                ]
                                            ],
                                            'format' => '%Y-%m-%dT%H:%M:%S'
                                        ]
                                    ]
                                ]
                            ],
                            [
                                '$addFields' => [
                                    'durationInMinutes' => [
                                        '$divide' => [
                                            ['$subtract' => ['$end', '$start']],
                                            1000 * 60
                                        ]
                                    ]
                                ]
                            ],
                            [
                                '$group' => [
                                    '_id' => null,
                                    'total_minutes' => ['$sum' => '$durationInMinutes']
                                ]
                            ],
                            [
                                '$addFields' => [
                                    'total_hours' => [
                                        '$floor' => [
                                            '$divide' => ['$total_minutes', 60]
                                        ]
                                    ],
                                    'remaining_minutes' => [
                                        '$mod' => ['$total_minutes', 60]
                                    ]
                                ]
                            ]
                        ],
                        'as' => 'timesheet_data'
                    ]
                ],
                [
                    '$addFields' => [
                        'calculated_total_raw_minutes' => [
                            '$add' => [
                                // Parent time
                                ['$ifNull' => [['$arrayElemAt' => ['$timesheet_data.total_minutes', 0]], 0]],
                                // Children time
                                ['$reduce' => [
                                    'input' => '$child_tasks',
                                    'initialValue' => 0,
                                    'in' => [
                                        '$add' => [
                                            '$$value',
                                            ['$add' => [
                                                ['$multiply' => [['$ifNull' => ['$$this.total_hours', 0]], 60]],
                                                ['$ifNull' => ['$$this.total_minutes', 0]]
                                            ]]
                                        ]
                                    ]
                                ]]
                            ]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'total_hours' => [
                            '$floor' => [
                                '$divide' => ['$calculated_total_raw_minutes', 60] // Calculate total hours
                            ]
                        ],
                        'total_minutes' => [
                            '$mod' => ['$calculated_total_raw_minutes', 60] // Calculate remaining minutes
                        ]
                    ]
                ],
                $sortStage,
                ['$project' => [
                    'task_id' => 1,
                    'title' => 1,
                    'project_id' => 1,
                    'project' => 1,
                    'milestone_id' => 1,
                    'milestone' => 1,
                    'status_id' => 1,
                    'task_status' => 1,
                    'task_type_id' => 1,
                    'task_type' => 1,
                    'priority' => 1,
                    'owner_id' => 1,
                    'owner' => 1,
                    'assignee_id' => 1,
                    'assignees' => 1,
                    'assignees_data' => 1,
                    'description' => 1,
                    'due_date' => 1,
                    'estimated_hours' => 1,
                    'attachment' => 1,
                    'created_by' => 1,
                    'created_bys' => 1,
                    'qa_id' => 1,
                    'qa' => 1,
                    'parent_task_id' => 1,
                    'parent_task' => 1,
                    'is_child_task' => 1,
                    'child_tasks' => 1,
                    'total_hours' => 1,
                    'total_minutes' => 1
                ]]
            ]);
            if ($limit !== -1) {
                $pipeline[] = ['$skip' => $skip];
                $pipeline[] = ['$limit' => $limit];
            }
            return $collection->aggregate($pipeline);
        });

        $totalCount = Tasks::raw(function ($collection) use ($matchStage) {
            $matchFilter = [];
            if (!empty($matchStage)) {
                $matchFilter = $matchStage;
            }
            return $collection->aggregate([
                ['$match' => (object)$matchFilter],
                ['$count' => 'total']
            ]);
        })->first()['total'] ?? 0;

        $tasks_data = [
            'data' => $tasks,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'total_pages' => $limit === -1 ? 1 : ceil($totalCount / $limit),
            ]
        ];

        // $projectsAssigned = Tasks::where('assignee_id', $request->user->id ?? $request->employee_id)
        //     ->distinct('project_id')
        //     ->count();

        $getDataFor = $request->user->id;

        if ($request->has('employee_id') || $request->has('assignees')) {
            if ($request->has('employee_id')) {
                $getDataFor = $request->employee_id;
            } elseif ($request->has('assignees')) {
                $getDataFor = $request->assignees;
            }
        }
        $project_id = $request->has('project_id');

        if ($project_id) {
            if ($request->user->role->slug === 'employee') {
                $projectsAssigned = Tasks::where('assignees', (string) $getDataFor)
                    ->where('project_id', $request->project_id)
                    ->count();
            } else {
                $projectsAssigned = Tasks::where('project_id', $request->project_id)
                    ->count();
            }
        } else {
            $projectsAssigned = Tasks::where('assignees', (string) $getDataFor)
                ->distinct('project_id')
                ->count();
        }

        $taskStatuses = Tasks::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],
                ['$group' => [
                    '_id' => '$status_id',
                    'total' => ['$sum' => 1]
                ]],
                ['$lookup' => [
                    'from' => 'task_statuses',
                    'let' => ['statusId' => ['$toObjectId' => '$_id']],
                    'pipeline' => [['$match' => ['$expr' => ['$eq' => ['$_id', '$$statusId']]]]],
                    'as' => 'task_status'
                ]],
                ['$unwind' => '$task_status'],
                ['$project' => [
                    'name' => '$task_status.name',
                    'total' => 1
                ]]
            ]);
        });

        if ($request->has('project_id')) {



            $estimated_project_hours = Tasks::raw(function ($collection) use ($request) {
                return $collection->aggregate([
                    ['$match' => ['project_id' => $request->project_id]],
                    [
                        '$group' => [
                            '_id' => null,
                            'total' => [
                                '$sum' => [
                                    '$convert' => [
                                        'input' => '$estimated_hours',
                                        'to' => 'int',
                                        'onError' => 0,
                                        'onNull' => 0
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            });

            $estimated_project_hours = $estimated_project_hours[0]['total'] ?? 0;


            $totalMinutes = Timesheet::raw(function ($collection) use ($request) {
                return $collection->aggregate([
                    ['$match' => ['project_id' => $request->project_id]],
                    ['$unwind' => '$dates'],
                    ['$unwind' => '$dates.time_log'],
                    [
                        '$project' => [
                            'minutes' => [
                                '$subtract' => [
                                    [
                                        '$add' => [
                                            ['$multiply' => [['$toInt' => ['$substr' => ['$dates.time_log.end_time', 0, 2]]], 60]],
                                            ['$toInt' => ['$substr' => ['$dates.time_log.end_time', 3, 2]]]
                                        ]
                                    ],
                                    [
                                        '$add' => [
                                            ['$multiply' => [['$toInt' => ['$substr' => ['$dates.time_log.start_time', 0, 2]]], 60]],
                                            ['$toInt' => ['$substr' => ['$dates.time_log.start_time', 3, 2]]]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        '$group' => [
                            '_id' => null,
                            'total' => ['$sum' => '$minutes']
                        ]
                    ]
                ]);
            });

            $totalMinutes = $totalMinutes[0]['total'] ?? 0;

            $hours = intdiv($totalMinutes, 60);
            $minutes = $totalMinutes % 60;

            $total_spent_hours = sprintf('%02d:%02d', $hours, $minutes);
        }

        return response()->json([
            'projects_assigned' => $projectsAssigned,
            'task_statuses' => $taskStatuses,
            'tasks_list' => $tasks_data,
            'estimated_project_hours' => $estimated_project_hours ?? null,
            'total_spent_hours' => $total_spent_hours ?? null,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|unique:tasks,title',
            'project_id' => 'required|exists:projects,_id',
            'milestone_id' => 'nullable|exists:milestones,_id',
            'status_id' => 'required|exists:task_statuses,_id',
            'task_type_id' => 'required|exists:task_types,_id',
            'priority' => 'required|string',
            'owner_id' => 'required|exists:users,_id',
            'qa_id' => 'nullable|exists:users,_id',
            'assignees' => 'required|array|min:1', // <-- updated for array
            'parent_task_id' => 'nullable|exists:tasks,_id',
            'is_child_task' => 'nullable',
            'assignees.*' => 'exists:users,_id',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'due_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($request) {
                    if (isset($request->start_date) && $value < $request->start_date) {
                        $fail('The due date cannot be earlier than the start date.');
                    }
                }
            ],
            'estimated_hours' => 'required|string',
            'attachment' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $service = app(FileUploadService::class);
        $attachment = '';
        if ($request->hasFile('attachment')) {
            $attachment = $service->upload($request->file('attachment'), 'uploads', $request->user->id);
        }

        // Check and update project assignee list
        $project = Project::find($request->project_id);
        if ($project) {
            $currentAssignees = $project->assignee ?? [];
            foreach ($request->assignees as $assigneeId) {
                if (!in_array($assigneeId, $currentAssignees)) {
                    $currentAssignees[] = $assigneeId;
                }
            }
            $project->assignee = $currentAssignees;
            $project->save();
        }

        $dueDate = Carbon::parse($request->due_date)->toIso8601String();
        $startDate = Carbon::parse($request->start_date)->toIso8601String();

        if ($request->parent_task_id) {
            $is_child_task = true;
        } else {
            $is_child_task = false;
        }

        $platform = Tasks::create([
            'title' => $request->title,
            'project_id' => $request->project_id,
            'milestone_id' => $request->milestone_id,
            'status_id' => $request->status_id,
            'task_type_id' => $request->task_type_id,
            'priority' => $request->priority,
            'owner_id' => $request->owner_id,
            'qa_id' => $request->qa_id,
            'parent_task_id' => $request->parent_task_id,
            'is_child_task' => $is_child_task,
            'assignees' => array_map('strval', $request->assignees),
            'description' => $request->description,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'estimated_hours' => $request->estimated_hours,
            'attachment' => $attachment,
            'created_by' => $request->user->id,
        ]);
        return response()->json($platform, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $task = Tasks::with(['owner', 'project', 'milestone', 'status', 'taskType', 'createdBy', 'qa', 'parentTask'])->findOrFail($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $assignees_data = [];
        if (is_array($task->assignees) && count($task->assignees)) {
            $assignees_data = User::whereIn('_id', $task->assignees)
                ->get(['id', 'name', 'last_name', 'personal_email', 'contact_number']) // add/remove fields as needed
                ->map(function ($user) {
                    return [
                        'id'    => (string) $user->id,
                        'name'  => $user->name,
                        'last_name'  => $user->last_name,
                        'email' => $user->personal_email,
                        'number' => $user->contact_number
                    ];
                })
                ->toArray();
        }

        // Convert the model to array and add assignees_data field
        $task_array = $task->toArray();
        $task_array['assignees_data'] = $assignees_data;
        return response()->json($task_array, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $task = Tasks::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|unique:tasks,title,' . $id,
            'project_id' => 'required|exists:projects,_id',
            'milestone_id' => 'nullable|exists:milestones,_id',
            'status_id' => 'required|exists:task_statuses,_id',
            'task_type_id' => 'required|exists:task_types,_id',
            'priority' => 'required|string',
            'owner_id' => 'required|exists:users,_id',
            'qa_id' => 'nullable|exists:users,_id',
            'parent_task_id' => 'nullable|exists:tasks,_id',
            'is_child_task' => 'nullable',
            'assignees' => 'required|array|min:1', // <-- updated for array
            'assignees.*' => 'exists:users,_id',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'due_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($request) {
                    if (isset($request->start_date) && $value < $request->start_date) {
                        $fail('The due date cannot be earlier than the start date.');
                    }
                }
            ],
            'estimated_hours' => 'required|string',
            'attachment' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = app(FileUploadService::class);
        if ($request->hasFile('attachment')) {
            // Delete old profile photo if exists
            if ($task->attachment) {
                $service->delete($task->attachment['file_path'], $task->attachment['media_id']);
            }
            $attachment = $service->upload($request->file('attachment'), 'uploads', $request->user->id);
            $task->attachment = $attachment;
        }

        // Check and update project assignee list
        $project = Project::find($request->project_id);
        if ($project) {
            $currentAssignees = $project->assignee ?? [];
            foreach ($request->assignees as $assigneeId) {
                if (!in_array($assigneeId, $currentAssignees)) {
                    $currentAssignees[] = $assigneeId;
                }
            }
            $project->assignee = $currentAssignees;
            $project->save();
        }

        $dueDate = Carbon::parse($request->due_date)->toIso8601String();
        $startDate = Carbon::parse($request->start_date)->toIso8601String();

        if ($request->parent_task_id) {
            $is_child_task = true;
        } else {
            $is_child_task = false;
        }

        $task->update(
            [
                'title' => $request->title,
                'project_id' => $request->project_id,
                'milestone_id' => $request->milestone_id,
                'status_id' => $request->status_id,
                'task_type_id' => $request->task_type_id,
                'priority' => $request->priority,
                'owner_id' => $request->owner_id,
                'qa_id' => $request->qa_id,
                'parent_task_id' => $request->parent_task_id,
                'is_child_task' => $is_child_task,
                'assignees' => array_map('strval', $request->assignees),
                'description' => $request->description,
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'estimated_hours' => $request->estimated_hours,
            ]
        );
        return response()->json($task, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $task = Tasks::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $task->delete();
        return response()->json(['message' => 'Task deleted successfully'], 200);
    }

    /**
     * For each milestone get the tasks that has task status as
     * complete and send response accordingly
     */
    public function getProjectMilestonesSummary(Request $request)
    {
        $projectId = $request->project_id;

        if (!$projectId) {
            return response()->json(['error' => 'Project ID is required'], 400);
        }

        $milestoneSummary = Milestones::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                // Match milestones for the given project
                ['$match' => ['project_id' => (string) $projectId]],

                // Lookup tasks associated with this milestone
                ['$lookup' => [
                    'from' => 'tasks',
                    'localField' => '_id',
                    'foreignField' => 'milestone_id',
                    'as' => 'tasks'
                ]],

                // Convert _id to Object format for consistency
                ['$addFields' => [
                    'milestone_id' => [
                        ['$toString' => '$_id']
                    ]
                ]],

                // Calculate total tasks and completed tasks
                ['$addFields' => [
                    'total_tasks' => ['$size' => '$tasks'],
                    'completed_tasks' => [
                        '$size' => [
                            '$filter' => [
                                'input' => '$tasks',
                                'as' => 'task',
                                'cond' => [
                                    '$eq' => [
                                        ['$toLower' => ['$ifNull' => ['$$task.status', '']]],
                                        'complete'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]],

                // Calculate completion percentage
                ['$addFields' => [
                    'completion_percentage' => [
                        '$cond' => [
                            ['$eq' => ['$total_tasks', 0]],
                            0, // If no tasks, percentage = 0
                            ['$multiply' => [['$divide' => ['$completed_tasks', '$total_tasks']], 100]]
                        ]
                    ]
                ]],

                // Sort milestones by 'order' field (ascending)
                ['$sort' => ['order' => 1]],

                // Project the final output fields
                ['$project' => [
                    '_id' => 0,
                    'milestone_id' => ['$arrayElemAt' => [['$milestone_id'], 0]], // Ensure consistent format
                    'milestone_name' => '$name',
                    'start_date' => '$start_date',
                    'end_date' => '$end_date',
                    'status' => '$status',
                    'color' => '$color',  // ✅ Include milestone color
                    'total_tasks' => 1,
                    'completed_tasks' => 1,
                    'completion_percentage' => 1
                ]]
            ]);
        });

        return response()->json(['milestone_summary' => $milestoneSummary]);
    }

    public function getTasksByProject(Request $request)
    {
        $userId = $request->user->id;
        $projectId = $request->project_id;

        if (!$projectId) {
            return response()->json(['error' => 'Project ID is required'], 400);
        }

        // Tasks grouped by status - UNCHANGED
        $tasksByStatus = Tasks::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                ['$match' => ['project_id' => (string) $projectId]],
                ['$addFields' => ['status_id' => ['$toObjectId' => '$status_id']]],
                ['$lookup' => [
                    'from' => 'task_statuses',
                    'localField' => 'status_id',
                    'foreignField' => '_id',
                    'as' => 'status_info'
                ]],
                ['$unwind' => ['path' => '$status_info', 'preserveNullAndEmptyArrays' => true]],
                ['$group' => [
                    '_id' => '$status_id',
                    'status_name' => ['$first' => '$status_info.name'],
                    'task_count' => ['$sum' => 1]
                ]],
                ['$sort' => ['status_name' => 1]]
            ]);
        });

        // Tasks grouped by assignee (UNWIND ASSIGNEES ARRAY)
        $tasksByAssignee = Tasks::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                ['$match' => ['project_id' => (string) $projectId]],
                ['$unwind' => '$assignees'],
                [
                    '$addFields' => [
                        'assignee_oid' => [
                            '$cond' => [
                                ['$eq' => ['$assignees', null]], // skip nulls
                                null,
                                ['$toObjectId' => '$assignees']
                            ]
                        ]
                    ]
                ],
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'assignee_oid',
                    'foreignField' => '_id',
                    'as' => 'assignee_info'
                ]],
                ['$unwind' => ['path' => '$assignee_info', 'preserveNullAndEmptyArrays' => true]],
                ['$group' => [
                    '_id' => '$assignee_oid',
                    'assignee_name' => ['$first' => '$assignee_info.name'],
                    'task_count' => ['$sum' => 1]
                ]],
                ['$sort' => ['assignee_name' => 1]]
            ]);
        });

        // Tasks grouped by assignee with status "to do" (UNWIND ASSIGNEES ARRAY)
        $tasksByAssigneeTodo = Tasks::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                ['$match' => ['project_id' => (string) $projectId]],
                ['$unwind' => '$assignees'],
                [
                    '$addFields' => [
                        'assignee_oid' => [
                            '$cond' => [
                                ['$eq' => ['$assignees', null]], // skip nulls
                                null,
                                ['$toObjectId' => '$assignees']
                            ]
                        ],
                        'status_oid' => ['$toObjectId' => '$status_id'],
                    ]
                ],
                ['$lookup' => [
                    'from' => 'task_statuses',
                    'localField' => 'status_oid',
                    'foreignField' => '_id',
                    'as' => 'status_info'
                ]],
                ['$unwind' => ['path' => '$status_info', 'preserveNullAndEmptyArrays' => true]],
                ['$match' => [
                    '$expr' => [
                        '$eq' => [
                            ['$toLower' => '$status_info.name'],
                            'to do'
                        ]
                    ]
                ]],
                ['$group' => [
                    '_id' => '$assignee_oid',
                    'task_count' => ['$sum' => 1]
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => '_id',
                    'foreignField' => '_id',
                    'as' => 'assignee_info'
                ]],
                ['$unwind' => ['path' => '$assignee_info', 'preserveNullAndEmptyArrays' => true]],
                ['$project' => [
                    'task_count' => 1,
                    'assignee_name' => '$assignee_info.name',
                    'assignee_email' => '$assignee_info.email',
                ]],
                ['$sort' => ['assignee_name' => 1]]
            ]);
        });

        // Project milestones summary (unchanged)
        $projectMilestones = Milestones::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                ['$match' => ['project_id' => (string) $projectId]],
                ['$group' => [
                    '_id' => '$project_id',
                    'total_milestones' => ['$sum' => 1],
                    'pending_milestones' => [
                        '$sum' => [
                            '$cond' => [
                                ['$ne' => [['$toLower' => '$status'], 'completed']],
                                1,
                                0
                            ]
                        ]
                    ],
                    'milestones' => ['$push' => [
                        'id' => '$_id',
                        'name' => '$name',
                        'start_date' => '$start_date',
                        'end_date' => '$end_date',
                        'status' => '$status',
                        'color' => '$color'
                    ]]
                ]],
                ['$addFields' => [
                    'completion_percentage' => [
                        '$multiply' => [
                            ['$divide' => [
                                ['$subtract' => ['$total_milestones', '$pending_milestones']],
                                '$total_milestones'
                            ]],
                            100
                        ]
                    ]
                ]]
            ]);
        });

        $tasksByStatusArray = iterator_to_array($tasksByStatus);
        $tasksByAssigneeArray = iterator_to_array($tasksByAssignee);
        $tasksByAssigneeTodoArray = iterator_to_array($tasksByAssigneeTodo);
        $projectMilestonesArray = iterator_to_array($projectMilestones);

        $response = [
            'tasks_by_status' => $tasksByStatusArray,
            'tasks_by_assignee' => $tasksByAssigneeArray,
            'tasks_by_assignee_todo_open' => $tasksByAssigneeTodoArray,
            'project_milestones_summary' => $projectMilestonesArray
        ];

        return response()->json($response);
    }

    public function getParentTasks(Request $request)
    {
        $tasks = Tasks::where('parent_task_id', null)
            ->where('is_child_task', false)
            ->get();
        $tasks = $tasks->map(function ($task) {
            return [
                'id' => $task->_id,
                'title' => $task->title,
            ];
        });
        return response()->json($tasks);
    }
}
