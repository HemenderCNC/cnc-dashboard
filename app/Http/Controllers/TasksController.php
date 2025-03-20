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
    
        $milestoneSummary = Tasks::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                // Filter tasks by project_id
                ['$match' => ['project_id' => (string) $projectId]],
    
                // Convert milestone_id and status_id to ObjectId
                ['$addFields' => [
                    'milestone_id' => ['$toObjectId' => '$milestone_id'],
                    'status_id' => ['$toObjectId' => '$status_id'],
                ]],
    
                // Lookup status details from task_statuses collection
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
    
                // Normalize status name (fixing the $trim issue)
                ['$addFields' => [
                    'normalized_status' => [
                        '$toLower' => ['$ifNull' => ['$status_info.name', '']]
                    ]
                ]],
    
                // Group tasks by milestone_id, counting total and completed tasks
                ['$group' => [
                    '_id' => '$milestone_id',
                    'total_tasks' => ['$sum' => 1],
                    'completed_tasks' => [
                        '$sum' => [
                            '$cond' => [['$eq' => ['$normalized_status', 'complete']], 1, 0]
                        ]
                    ]
                ]],
    
                // Calculate milestone completion percentage
                ['$addFields' => [
                    'completion_percentage' => [
                        '$cond' => [
                            ['$eq' => ['$total_tasks', 0]], 0,
                            ['$multiply' => [['$divide' => ['$completed_tasks', '$total_tasks']], 100]]
                        ]
                    ]
                ]],
    
                // Lookup milestone details from milestones collection
                ['$lookup' => [
                    'from' => 'milestones',
                    'localField' => '_id',
                    'foreignField' => '_id',
                    'as' => 'milestone_info'
                ]],
    
                ['$unwind' => [
                    'path' => '$milestone_info',
                    'preserveNullAndEmptyArrays' => true // Avoid errors if no matching milestone
                ]],
    
                // Project final output
                ['$project' => [
                    '_id' => 0,
                    'milestone_id' => ['$_id'],
                    'milestone_name' => '$milestone_info.name',
                    'start_date' => '$milestone_info.start_date',
                    'end_date' => '$milestone_info.end_date',
                    'status' => '$milestone_info.status',
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
        $userId = $request->user->id; // Get logged-in user's ID
        $projectId = $request->project_id; // Get project ID from request
    
        // Validate input
        if (!$projectId) {
            return response()->json(['error' => 'Project ID is required'], 400);
        }
    
        // Tasks grouped by status
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
    
        // Tasks grouped by assignee
        $tasksByAssignee = Tasks::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                ['$match' => ['project_id' => (string) $projectId]],
                ['$addFields' => ['assignee_id' => ['$toObjectId' => '$assignee_id']]],
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'assignee_id',
                    'foreignField' => '_id',
                    'as' => 'assignee_info'
                ]],
                ['$unwind' => ['path' => '$assignee_info', 'preserveNullAndEmptyArrays' => true]],
                ['$group' => [
                    '_id' => '$assignee_id',
                    'assignee_name' => ['$first' => '$assignee_info.name'],
                    'task_count' => ['$sum' => 1]
                ]],
                ['$sort' => ['assignee_name' => 1]]
            ]);
        });
    
        // Tasks grouped by assignee where status is "To Do" OR Open tasks
        $tasksByAssigneeTodo = Tasks::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                ['$match' => ['project_id' => (string) $projectId]],
                ['$addFields' => [
                    'status_id' => ['$toObjectId' => '$status_id'],
                    'assignee_id' => ['$toObjectId' => '$assignee_id']
                ]],
                ['$lookup' => [
                    'from' => 'task_statuses',
                    'localField' => 'status_id',
                    'foreignField' => '_id',
                    'as' => 'status_info'
                ]],
                ['$unwind' => ['path' => '$status_info', 'preserveNullAndEmptyArrays' => true]],
                ['$match' => [
                    '$expr' => ['$eq' => [['$toLower' => '$status_info.name'], 'to do']]
                ]],
                ['$group' => [
                    '_id' => '$assignee_id',
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
    
        // Project milestones summary OR Project Progress
        /**
         * Calculates all milestones that are in pending status and return result accordingly
         */
        $projectMilestones = Milestones::raw(function ($collection) use ($projectId) {
            return $collection->aggregate([
                ['$match' => ['project_id' => (string) $projectId]],
                ['$group' => [
                    '_id' => '$project_id',
                    'total_milestones' => ['$sum' => 1],
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
    
        // Prepare response
        $response = [
            'tasks_by_status' => $tasksByStatusArray,
            'tasks_by_assignee' => $tasksByAssigneeArray,
            'tasks_by_assignee_todo_open' => $tasksByAssigneeTodoArray,
            'project_milestones_summary' => $projectMilestonesArray
        ];
    
        return response()->json($response);
    }
    

}
