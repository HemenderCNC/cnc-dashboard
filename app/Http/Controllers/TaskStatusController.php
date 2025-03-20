<?php

namespace App\Http\Controllers;

use App\Models\TaskStatus;
use App\Models\Tasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskStatusController extends Controller
{
    public function index()
    {
        $taskStatuses = TaskStatus::orderBy('created_at', 'desc')->get()->map(function ($status) {
            $taskCount = Tasks::where('status_id', $status->_id)->count();

            return [
                'id' => (string) $status->_id,
                'name' => $status->name,
                'tasks_count' => $taskCount,
                'created_at' => $status->created_at,
                'updated_at' => $status->updated_at,
            ];
        });

        return response()->json($taskStatuses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:task_statuses,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = strtolower(trim($request->name)); // Trim spaces and convert to lowercase

        $taskstatus = TaskStatus::create(['name' => $name]);
        // $taskstatus = TaskStatus::create($request->only('name'));
        return response()->json($taskstatus, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $taskstatus = TaskStatus::find($id);
        if (!$taskstatus) {
            return response()->json(['message' => 'Task Status not found'], 404);
        }
        return response()->json($taskstatus, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $taskstatus = TaskStatus::find($id);
        if (!$taskstatus) {
            return response()->json(['message' => 'Task Status not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:task_statuses,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = strtolower(trim($request->name)); // Trim spaces and convert to lowercase

        $taskstatus->update(['name' => $name]);
        // $taskstatus->update($request->only('name'));
        return response()->json($taskstatus, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $taskstatus = TaskStatus::find($id);
        if (!$taskstatus) {
            return response()->json(['message' => 'Task Status not found'], 404);
        }

        $taskstatus->delete();
        return response()->json(['message' => 'Task Status deleted successfully'], 200);
    }
}
