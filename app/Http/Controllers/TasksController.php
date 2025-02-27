<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class TasksController extends Controller
{
    public function index(Request $request)
    {
        $matchStage = (object)[]; // Ensure it's an object, not an empty array

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

        // Ensure matchStage is not empty
        if (empty((array) $matchStage)) {
            $matchStage = (object)[]; // Empty object for MongoDB
        }

        // MongoDB Aggregation Pipeline
        $projects = Tasks::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],  // Apply Filters
                ['$lookup' => [
                    'from' => 'projects',
                    'let' => ['statusId' => ['$toObjectId' => '$project_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$statusId']]]]
                    ],
                    'as' => 'project'
                ]],
                ['$lookup' => [
                    'from' => 'milestones',   // Collection name for Clients
                    'let' => ['milestoneId' => ['$toObjectId' => '$milestone_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$milestoneId']]]]
                    ],
                    'as' => 'milestone'
                ]],
                ['$lookup' => [
                    'from' => 'task_statuses',   // Collection name for Clients
                    'let' => ['taskStatusId' => ['$toObjectId' => '$status_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskStatusId']]]]
                    ],
                    'as' => 'task_status'
                ]],
                ['$lookup' => [
                    'from' => 'task_types',   // Collection name for Clients
                    'let' => ['taskTypeId' => ['$toObjectId' => '$task_type_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskTypeId']]]]
                    ],
                    'as' => 'task_type'
                ]],
                ['$lookup' => [
                    'from' => 'users',   // Collection name for Clients
                    'let' => ['ownerId' => ['$toObjectId' => '$owner_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$ownerId']]]]
                    ],
                    'as' => 'owner'
                ]],
                ['$lookup' => [
                    'from' => 'users',   // Collection name for Clients
                    'let' => ['assigneeId' => ['$toObjectId' => '$assignee_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$assigneeId']]]]
                    ],
                    'as' => 'assignees'
                ]],
                ['$lookup' => [
                    'from' => 'users',   // Collection name for Clients
                    'let' => ['createdBy' => ['$toObjectId' => '$created_by']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$createdBy']]]]
                    ],
                    'as' => 'created_bys'
                ]],
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
        });

        return response()->json($projects, 200);
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
            'due_date' => $request->due_date,
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
        if ($request->hasFile('attachment')) {
            // Delete old profile photo if exists
            if ($task->attachment) {
                $service->delete($task->attachment['file_path']);
            }
            $attachment = $service->upload($request->file('attachment'), 'uploads', $request->user->id);
            $task->attachment = $attachment;
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
                'assignee_id' => $request->assignee_id,
                'description' => $request->description,
                'due_date' => $request->due_date,
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
}
