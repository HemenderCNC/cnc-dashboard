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
                ['$match' => $matchStage],  // Apply Filters

                // Lookup Task
                ['$lookup' => [
                    'from' => 'tasks',
                    'let' => ['taskId' => ['$toObjectId' => '$task_id']], // Convert task_id to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskId']]]],
                        // Lookup Task Type inside Tasks
                        ['$lookup' => [
                            'from' => 'task_types',  // Assuming the collection name is `task_types`
                            'let' => ['taskTypeId' => ['$toObjectId' => '$task_type_id']], // Convert task_type_id to ObjectId
                            'pipeline' => [
                                ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskTypeId']]]],
                                ['$project' => ['_id' => 0, 'name' => 1]] // Only fetch task type name
                            ],
                            'as' => 'task_type'
                        ]],
                    ],
                    'as' => 'task'
                ]],

                // Lookup Project
                ['$lookup' => [
                    'from' => 'projects',
                    'let' => ['projectId' => ['$toObjectId' => '$project_id']], // Convert project_id to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$projectId']]]]
                    ],
                    'as' => 'project'
                ]],

                // Lookup User
                ['$lookup' => [
                    'from' => 'users',
                    'let' => ['userId' => ['$toObjectId' => '$employee_id']], // Convert employee_id to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$userId']]]]
                    ],
                    'as' => 'user'
                ]],
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
        $time_log[] = array(
            'start_time' => now()->format('H:i'),
            'end_time' => now()->addMinute()->format('H:i'),
        );
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
        $currentDate = Carbon::now()->toDateString();
        $session = LoginSession::where('employee_id', $userId)->where('date', $currentDate)->first();
        if ($session) {
            $session->break = false;
            $session->save();
        }
        $timesheet->save();
        return response()->json($timesheet);
    }
    public function completeTask(Request $request,$id){
        $userId = $request->user->id;
        $currentDate = Carbon::now()->toDateString();
        $timesheet = Timesheet::where('id', $id)->first();
        if (!$timesheet) {
            return response()->json(['message' => 'Timesheet not found'], 404);
        }
        $timesheet->status = 'completed';
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
        $timesheet->save();
        return response()->json($timesheet);
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
