<?php

namespace App\Http\Controllers;

use App\Models\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskStatusController extends Controller
{
    public function index()
    {
        return response()->json(TaskStatus::all(), 200);
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
        $taskstatus = TaskStatus::create($request->only('name'));
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

        $taskstatus->update($request->only('name'));
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
