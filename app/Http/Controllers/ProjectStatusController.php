<?php

namespace App\Http\Controllers;

use App\Models\ProjectStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProjectStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ProjectStatus::orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:project_statuses,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = strtolower(trim($request->name)); // Trim spaces and convert to lowercase

        $projectstatus = ProjectStatus::create(['name' => $name]);
        // $projectstatus = ProjectStatus::create($request->only('name'));
        return response()->json($projectstatus, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $projectstatus = ProjectStatus::find($id);
        if (!$projectstatus) {
            return response()->json(['message' => 'Project Status not found'], 404);
        }
        return response()->json($projectstatus, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $projectstatus = ProjectStatus::find($id);
        if (!$projectstatus) {
            return response()->json(['message' => 'Project Status not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:project_statuses,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = strtolower(trim($request->name)); // Trim spaces and convert to lowercase

        $projectstatus->update(['name' => $name]);
        // $projectstatus->update($request->only('name'));
        return response()->json($projectstatus, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $projectstatus = ProjectStatus::find($id);
        if (!$projectstatus) {
            return response()->json(['message' => 'Project Status not found'], 404);
        }

        $projectstatus->delete();
        return response()->json(['message' => 'Project Status deleted successfully'], 200);
    }
}
