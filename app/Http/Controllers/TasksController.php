<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
use App\Models\Milestones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use Carbon\Carbon;

class TasksController extends Controller
{
    public function index(Request $request)
    {
        $matchStage = (object)[]; // Ensure it's an object, not an empty array

        if ($request->user->role->name === 'Employee') {
            $matchStage->assignee_id = $request->user->id;
        }
        else if ($request->has('employee_id')) {
            $matchStage->assignee_id = $request->employee_id;
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
        if ($request->has('milestone_id')) {
            $matchStage->milestone_id = $request->milestone_id;
        }

        // Filter by project status
        if ($request->has('status_id')) {
            $matchStage->status_id = $request->status_id;
        }
        if ($request->has('task_type_id')) {
            $matchStage->task_type_id = $request->task_type_id;
        }
        if ($request->has('priority')) {
            $matchStage->priority = $request->priority;
        }
        if ($request->has('owner_id')) {
            $matchStage->owner_id = $request->owner_id;
        }

        // Filter by platforms (array match)
        if ($request->has('assignee_id')) {
            $matchStage->assignee_id = ['$in' => array_map('strval', (array) $request->assignee_id)];
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $matchStage->due_date  = [
                '$gte' => $startDate,
                '$lte' => $endDate
            ];
        }
        // Ensure matchStage is not empty
        if (empty((array) $matchStage)) {
            $matchStage = (object)[]; // Empty object for MongoDB
        }
        $sortStage = ['$sort' => ['created_at' => -1]]; // Default sorting by created_at (Descending)
        $matchDueDate=null;
        if ($request->has('sort') && $request->sort === 'due_date') {
            $todayTimestamp = Carbon::today()->toIso8601String(); // Convert to milliseconds
            $matchDueDate = ['$match' => ['due_date' => ['$gte' => $todayTimestamp]]];
            $sortStage = ['$sort' => ['due_date' => 1]];
        }


        // MongoDB Aggregation Pipeline
        $tasks = Tasks::raw(function ($collection) use ($matchStage, $sortStage, $matchDueDate) {
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
                    'let' => ['assigneeId' => ['$toObjectId' => '$assignee_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$assigneeId']]]],
                        ['$lookup' => [
                            'from' => 'designations', // Replace with the actual name of the designation collection
                            'let' => ['designationId' => ['$toObjectId' => '$designation_id']],
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$designationId']]]]
                            ],
                            'as' => 'designation'
                        ]]
                    ],
                    'as' => 'assignees'
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'let' => ['createdBy' => ['$toObjectId' => '$created_by']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$createdBy']]]]
                    ],
                    'as' => 'created_bys'
                ]],
                $sortStage,
                ['$project' => [
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
                    'description' => 1,
                    'due_date' => 1,
                    'estimated_hours' => 1,
                    'attachment' => 1,
                    'created_by' => 1,
                    'created_bys' => 1,
                ]]
            ]);
            return $collection->aggregate($pipeline);
        });

        $projectsAssigned = Tasks::where('assignee_id', $request->user->id ?? $request->employee_id)
        ->distinct('project_id')
        ->count();

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

        return response()->json([
            'projects_assigned' => $projectsAssigned,
            'task_statuses' => $taskStatuses,
            'tasks_list' => $tasks,
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
            'milestone_id' => 'required|exists:milestones,_id',
            'status_id' => 'required|exists:task_statuses,_id',
            'task_type_id' => 'required|exists:task_types,_id',
            'priority' => 'required|string',
            'owner_id' => 'required|exists:users,_id',
            'assignee_id' => 'required|exists:users,_id',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
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
        $dueDate = Carbon::parse($request->due_date)->toIso8601String();
        $platform = Tasks::create([
            'title' => $request->title,
            'project_id' => $request->project_id,
            'milestone_id' => $request->milestone_id,
            'status_id' => $request->status_id,
            'task_type_id' => $request->task_type_id,
            'priority' => $request->priority,
            'owner_id' => $request->owner_id,
            'assignee_id' => $request->assignee_id,
            'description' => $request->description,
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
        $task = Tasks::with(['owner','assignee','project','milestone','status','taskType','createdBy'])->find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        return response()->json($task, 200);
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
            'title' => 'required|string|unique:tasks,title,'.$id,
            'project_id' => 'required|exists:projects,_id',
            'milestone_id' => 'required|exists:milestones,_id',
            'status_id' => 'required|exists:task_statuses,_id',
            'task_type_id' => 'required|exists:task_types,_id',
            'priority' => 'required|string',
            'owner_id' => 'required|exists:users,_id',
            'assignee_id' => 'required|exists:users,_id',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
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
                $service->delete($task->attachment['file_path'],$task->attachment['media_id']);
            }
            $attachment = $service->upload($request->file('attachment'), 'uploads', $request->user->id);
            $task->attachment = $attachment;
        }
        $dueDate = Carbon::parse($request->due_date)->toIso8601String();
        $task->update(
            [
                'title' => $request->title,
                'project_id' => $request->project_id,
                'milestone_id' => $request->milestone_id,
                'status_id' => $request->status_id,
                'task_type_id' => $request->task_type_id,
                'priority' => $request->priority,
                'owner_id' => $request->owner_id,
                'assignee_id' => $request->assignee_id,
                'description' => $request->description,
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

    public function getTasksByProject(Request $request)
    {
        $userId = $request->user->id; // Get logged-in user's ID
        $projectId = $request->project_id; // Get project ID from request
        $perPage = (int) ($request->query('per_page', 10)); // Number of results per page (default: 10)

        // Validate input
        if (!$projectId) {
            return response()->json(['error' => 'Project ID is required'], 400);
        }

        // Use aggregation pipeline for grouping
        $tasksByStatus = Tasks::raw(function ($collection) use ($projectId, $userId, $perPage, $request) {
            $page = (int) $request->query('page', 1);
            $skip = ($page - 1) * $perPage;
        
            return $collection->aggregate([
                ['$match' => [
                    'project_id' => (string) $projectId,  // Ensure it's treated as a string
                    // 'assignee_id' => (string) $userId
                ]],
                // Convert status_id to ObjectId
                ['$addFields' => [
                    'status_id' => ['$toObjectId' => '$status_id']
                ]],
                ['$lookup' => [
                    'from' => 'task_statuses',
                    'localField' => 'status_id',
                    'foreignField' => '_id',
                    'as' => 'status_info'
                ]],
        
                ['$unwind' => [
                    'path' => '$status_info',
                    'preserveNullAndEmptyArrays' => true // Avoid errors if no matching status
                ]],
        
                // ['$group' => [
                //     '_id' => '$status_id',
                //     'status_name' => ['$first' => '$status_info.name'],
                //     'tasks' => ['$push' => [
                //         'task_id' => '$_id',
                //         'title' => '$title',
                //         'description' => '$description',
                //         'due_date' => '$due_date',
                //         'estimated_hours' => '$estimated_hours'
                //     ]]
                // ]],

                ['$group' => [
                    '_id' => '$status_id',
                    'status_name' => ['$first' => '$status_info.name'],
                    'task_count' => ['$sum' => 1]  // Count tasks instead of listing them
                ]],
        
                ['$sort' => ['status_name' => 1]],
                ['$skip' => $skip],
                ['$limit' => $perPage]
            ]);
        });


        $tasksByAssignee = Tasks::raw(function ($collection) use ($projectId, $userId, $perPage, $request) {
            $page = (int) $request->query('page', 1);
            $skip = ($page - 1) * $perPage;
        
            return $collection->aggregate([
                ['$match' => [
                    'project_id' => (string) $projectId,  // Ensure it's treated as a string
                    // 'assignee_id' => (string) $userId
                ]],
                // Convert status_id to ObjectId
                ['$addFields' => [
                    'assignee_id' => ['$toObjectId' => '$assignee_id']
                ]],
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'assignee_id',
                    'foreignField' => '_id',
                    'as' => 'assignee_info'
                ]],
        
                ['$unwind' => [
                    'path' => '$assignee_info',
                    'preserveNullAndEmptyArrays' => true // Avoid errors if no matching status
                ]],
        
                ['$group' => [
                    '_id' => '$assignee_id',
                    'assignee_name' => ['$first' => '$assignee_info.name'],
                    'task_count' => ['$sum' => 1]  // Count tasks instead of listing them
                ]],
        
                ['$sort' => ['assignee_name' => 1]],
                ['$skip' => $skip],
                ['$limit' => $perPage]
            ]);
        });


        // New Query: Get tasks by assignee where status is "To Do"
        $tasksByAssigneeTodo = Tasks::raw(function ($collection) use ($projectId, $perPage, $request) {
            $page = (int) $request->query('page', 1);
            $skip = ($page - 1) * $perPage;
        
            return $collection->aggregate([
                ['$match' => [
                    'project_id' => (string) $projectId
                ]],
                ['$addFields' => [
                    'status_id' => ['$toObjectId' => '$status_id'],
                    'assignee_id' => ['$toObjectId' => '$assignee_id']
                ]],
                // Lookup status name from task_statuses
                ['$lookup' => [
                    'from' => 'task_statuses',
                    'localField' => 'status_id',
                    'foreignField' => '_id',
                    'as' => 'status_info'
                ]],
                ['$unwind' => [
                    'path' => '$status_info',
                    'preserveNullAndEmptyArrays' => true
                ]],
                // ['$match' => [
                //     'status_info.name' => 'To Do' // Only fetch tasks with "To Do" status
                // ]],
                ['$match' => [
                    '$expr' => [
                        '$eq' => [['$toLower' => '$status_info.name'], 'to do']
                    ]
                ]],
                ['$group' => [
                    '_id' => '$assignee_id',
                    'task_count' => ['$sum' => 1]
                ]],
                // Lookup user details for assignee
                ['$lookup' => [
                    'from' => 'users', // Replace with your actual users collection name
                    'localField' => '_id',
                    'foreignField' => '_id',
                    'as' => 'assignee_info'
                ]],
                ['$unwind' => [
                    'path' => '$assignee_info',
                    'preserveNullAndEmptyArrays' => true
                ]],
                ['$project' => [
                    'task_count' => 1,
                    'assignee_name' => '$assignee_info.name',
                    'assignee_email' => '$assignee_info.email',
                ]],
                ['$sort' => ['assignee_name' => 1]], // Sort by assignee name
                ['$skip' => $skip],
                ['$limit' => $perPage]
            ]);
        });
        
        //Project Milestone summary
        $projectMilestones = Milestones::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                // Match milestones by project_id
                ['$match' => [
                    'project_id' => (string) $projectId
                ]],
                // Convert status to lowercase and group by project_id
                ['$group' => [
                    '_id' => '$project_id',
                    'total_milestones' => ['$sum' => 1], // Count total milestones
                    'pending_milestones' => [
                        '$sum' => [
                            '$cond' => [['$eq' => [['$toLower' => '$status'], 'pending']], 1, 0]
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
                // Calculate completion percentage
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
        
        


        // Convert results to arrays
        $tasksByStatusArray = iterator_to_array($tasksByStatus);
        $tasksByAssigneeArray = iterator_to_array($tasksByAssignee);
        $tasksByAssigneeTodoArray = iterator_to_array($tasksByAssigneeTodo);
        $projectMilestonesArray = iterator_to_array($projectMilestones);

        // Merge both results
        $response = [
            'tasks_by_status' => $tasksByStatusArray,
            'tasks_by_assignee' => $tasksByAssigneeArray,
            'tasks_by_assignee_todo' => $tasksByAssigneeTodoArray,
            'project_milestones_summary' => $projectMilestonesArray
        ];
        
        return response()->json($response);

    }

}
