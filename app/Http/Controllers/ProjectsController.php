<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class ProjectsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Project::all(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|unique:projects,name',
            'project_industry' => 'required|string',
            'project_type' => 'required|string',
            'priority' => 'required|string',
            'budget' => 'required|string',
            'project_status_id' => 'required|exists:project_statuses,_id',
            'platform_id' => 'required|exists:platforms,_id',
            'language_id' => 'required|exists:languages,_id',
            'estimated_start_date' => 'required|date',
            'estimated_end_date' => 'required|date',
            'actual_start_date' => 'required|date',
            'actual_end_date' => 'required|date',
            'client_id' => 'required|exists:clients,_id',
            'assignee' => 'nullable|array',
            'assignee.*' => 'exists:users,_id',
            'project_manager_id' => 'nullable|exists:users,_id',
            'other_details' => 'nullable|file|mimes:pdf,jpeg,png|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $service = app(FileUploadService::class);
        $other_details = '';
        if ($request->hasFile('other_details')) {
            $other_details = $service->upload($request->file('other_details'), 'uploads', $request->user->id);
        }
        $platform = Project::create([
            'project_name' => $request->project_name,
            'project_industry' => $request->project_industry,
            'project_type' => $request->project_type,
            'priority' => $request->priority,
            'budget' => $request->budget,
            'project_status_id' => $request->project_status_id,
            'platform_id' => $request->platform_id,
            'language_id' => $request->language_id,
            'estimated_start_date' => $request->estimated_start_date,
            'estimated_end_date' => $request->estimated_end_date,
            'actual_start_date' => $request->actual_start_date,
            'actual_end_date' => $request->actual_end_date,
            'client_id' => $request->client_id,
            'assignee' => $request->assignee,
            'project_manager_id' => $request->project_manager_id,
            'other_details' => $other_details,
            'created_by' => $request->user->id,
        ]);
        return response()->json($platform, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $platform = Platform::find($id);
        if (!$platform) {
            return response()->json(['message' => 'Platform not found'], 404);
        }
        return response()->json($platform, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $platform = Platform::find($id);
        if (!$platform) {
            return response()->json(['message' => 'Platform not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:platforms,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $platform->update($request->only('name'));
        return response()->json($platform, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $platform = Platform::find($id);
        if (!$platform) {
            return response()->json(['message' => 'Platform not found'], 404);
        }

        $platform->delete();
        return response()->json(['message' => 'Platform deleted successfully'], 200);
    }
}
