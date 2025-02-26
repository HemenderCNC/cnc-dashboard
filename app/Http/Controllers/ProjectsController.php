<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class ProjectsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $matchStage = (object)[]; // Ensure it's an object, not an empty array

        // Filter by project name (partial match)
        if ($request->has('project_name')) {
            $matchStage->project_name = ['$regex' => $request->project_name, '$options' => 'i'];
        }

        // Filter by client ID
        if ($request->has('client_id')) {
            $matchStage->client_id = $request->client_id;
        }

        // Filter by project industry
        if ($request->has('project_industry')) {
            $matchStage->project_industry = $request->project_industry;
        }

        // Filter by project status
        if ($request->has('project_status_id')) {
            $matchStage->project_status_id = $request->project_status_id;
        }

        // Filter by platforms (array match)
        if ($request->has('platforms')) {
            $matchStage->platforms = ['$in' => array_map('strval', (array) $request->platforms)];
        }

        // Filter by languages (array match)
        if ($request->has('languages')) {
            $matchStage->languages = ['$in' => array_map('strval', (array) $request->languages)];
        }

        // Ensure matchStage is not empty
        if (empty((array) $matchStage)) {
            $matchStage = (object)[]; // Empty object for MongoDB
        }

        // MongoDB Aggregation Pipeline
        $projects = Project::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],  // Apply Filters
                ['$lookup' => [
                    'from' => 'project_statuses',
                    'let' => ['statusId' => ['$toObjectId' => '$project_status_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$statusId']]]]
                    ],
                    'as' => 'project_status'
                ]],
                ['$lookup' => [
                    'from' => 'clients',   // Collection name for Clients
                    'let' => ['clientId' => ['$toObjectId' => '$client_id']], // Convert to ObjectId
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$clientId']]]]
                    ],
                    'as' => 'client'
                ]],
                ['$lookup' => [
                    'from' => 'users',   // Collection name for Users (Project Manager)
                    'localField' => 'project_manager_id',
                    'foreignField' => '_id',
                    'as' => 'project_manager'
                ]],
                ['$lookup' => [
                    'from' => 'users',   // Collection name for Users (Created By)
                    'localField' => 'created_by',
                    'foreignField' => '_id',
                    'as' => 'created_bys'
                ]],
                ['$project' => [
                    'project_name' => 1,
                    'project_industry' => 1,
                    'project_type' => 1,
                    'priority' => 1,
                    'budget' => 1,
                    'estimated_start_date' => 1,
                    'estimated_end_date' => 1,
                    'actual_start_date' => 1,
                    'actual_end_date' => 1,
                    'client_id' => 1,
                    'client' => 1,
                    'assignee' => 1,
                    'estimated_hours' => 1,
                    'project_manager_id' => 1,
                    'project_status_id' => 1,
                    'project_status' => 1,
                    'created_by' => 1,
                    'platforms' => 1,
                    'languages' => 1,
                    'other_details' => 1,
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
            'project_name' => 'required|string|unique:projects,name',
            'project_industry' => 'required|string',
            'project_type' => 'required|string',
            'estimated_hours' => 'nullable|string',
            'project_description' => 'nullable|string',
            'priority' => 'required|string',
            'budget' => 'required|string',
            'project_status_id' => 'required|exists:project_statuses,_id',
            'platforms' => 'required|array',
            'platforms.*' => 'exists:platforms,_id',
            'languages' => 'required|array',
            'languages.*' => 'exists:languages,_id',
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
            'estimated_hours' => $request->estimated_hours,
            'project_description' => $request->project_description,
            'priority' => $request->priority,
            'budget' => $request->budget,
            'project_status_id' => $request->project_status_id,
            'platforms' => $request->platforms,
            'languages' => $request->languages,
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
        $project = project::with(['projectStatus','client','projectManager','createdBy'])->find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        $project->assignees_data = $project->assignees_data;
        $project->languages_data = $project->languages_data;
        $project->platforms_data = $project->platforms_data;

        return response()->json($project, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|unique:projects,name,'. $id,
            'project_industry' => 'required|string',
            'project_type' => 'required|string',
            'estimated_hours' => 'nullable|string',
            'project_description' => 'nullable|string',
            'priority' => 'required|string',
            'budget' => 'required|string',
            'project_status_id' => 'required|exists:project_statuses,_id',
            'platforms' => 'required|array',
            'platforms.*' => 'exists:platforms,_id',
            'languages' => 'required|array',
            'languages.*' => 'exists:languages,_id',
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
        if ($request->hasFile('other_details')) {
            // Delete old profile photo if exists
            if ($project->other_details) {
                $service->delete($project->other_details['file_path']);
            }
            $other_details = $service->upload($request->file('other_details'), 'uploads', $request->user->id);
            $project->other_details = $other_details;
        }
        $project->update(
            [
                'project_name' => $request->project_name,
                'project_industry' => $request->project_industry,
                'project_type' => $request->project_type,
                'estimated_hours' => $request->estimated_hours,
                'project_description' => $request->project_description,
                'priority' => $request->priority,
                'budget' => $request->budget,
                'project_status_id' => $request->project_status_id,
                'platforms' => $request->platforms,
                'languages' => $request->languages,
                'estimated_start_date' => $request->estimated_start_date,
                'estimated_end_date' => $request->estimated_end_date,
                'actual_start_date' => $request->actual_start_date,
                'actual_end_date' => $request->actual_end_date,
                'client_id' => $request->client_id,
                'assignee' => $request->assignee,
                'project_manager_id' => $request->project_manager_id,
            ]
        );
        return response()->json($project, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $project->delete();
        return response()->json(['message' => 'Project deleted successfully'], 200);
    }
}
