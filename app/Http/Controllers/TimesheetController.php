<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Timesheet;
use App\Models\Tasks;
use Illuminate\Support\Facades\Validator;

class TimesheetController extends Controller
{
    // Get all timesheets for an employee
    public function index(Request $request)
    {
        /**Code without filter or search */
        // $timesheets = Timesheet::where('employee_id', $request->user->id)
        //     ->with(['project', 'task', 'user'])
        //     ->get();

        // return response()->json($timesheets);

        // // For management Level return all Timesheets
        // return response()->json(Timesheet::all(), 200);
        /**Code without filter or search END */


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
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$taskId']]]]
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
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'required|exists:tasks,id',
            'date' => 'required|date',
            'hours' => 'required|integer|min:0|max:24',
            'minutes' => 'required|integer|min:0|max:59',
            'work_description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $timesheet = Timesheet::create([
            'project_id' => $request->project_id,
            'task_id' => $request->task_id,
            'date' => $request->date,
            'hours' => $request->hours,
            'minutes' => $request->minutes,
            'work_description' => $request->work_description,
            'employee_id' => $request->user->id,
            'updated_by' => $request->user->id,
        ]);

        return response()->json($timesheet, 201);
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
            'hours' => 'required|integer|min:0|max:24',
            'minutes' => 'required|integer|min:0|max:59',
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
            'hours' => $request->hours,
            'minutes' => $request->minutes,
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
