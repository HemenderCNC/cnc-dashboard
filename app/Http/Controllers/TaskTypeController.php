<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TaskType;
use Illuminate\Support\Facades\Validator;

class TaskTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(TaskType::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:task_types,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $taskType = TaskType::create([
            'name' => $request->name,
        ]);

        return response()->json($taskType, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $taskType = TaskType::find($id);
        if (!$taskType) {
            return response()->json(['message' => 'Task Type not found'], 404);
        }

        return response()->json($taskType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $taskType = TaskType::find($id);
        if (!$taskType) {
            return response()->json(['message' => 'Task Type not found'], 404);
        }

        $request->validate([
            'name' => 'required|unique:task_types,name,' . $id,
        ]);

        $taskType->update(['name' => $request->name]);

        return response()->json($taskType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $taskType = TaskType::find($id);
        if (!$taskType) {
            return response()->json(['message' => 'Task Type not found'], 404);
        }

        $taskType->delete();
        return response()->json(['message' => 'Task Type deleted successfully']);
    }
}
