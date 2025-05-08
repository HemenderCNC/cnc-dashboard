<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;
class ProjectsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $matchStage = (object)[]; // Ensure it's an object, not an empty array
        if ($request->user->role->name === 'employee') {
            $user_id = $request->user->id;
            $matchStage->assignee = ['$in' => array_map('strval', (array) $user_id)];
        }
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

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = new UTCDateTime(Carbon::parse($request->start_date)->getTimestampMs());
            $endDate = new UTCDateTime(Carbon::parse($request->end_date)->getTimestampMs());

            $matchStage->created_at = [
                '$gte' => $startDate,
                '$lte' => $endDate
            ];
        }
        // Ensure matchStage is not empty
        if (empty((array) $matchStage)) {
            $matchStage = (object)[]; // Empty object for MongoDB
        }

        // MongoDB Aggregation Pipeline
        $projects = Project::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],  // Apply Filters
        
                // Lookup project_status
                ['$lookup' => [
                    'from' => 'project_statuses',
                    'let' => ['statusId' => ['$toObjectId' => '$project_status_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$statusId']]]]
                    ],
                    'as' => 'project_status'
                ]],
        
                // Lookup client
                ['$lookup' => [
                    'from' => 'clients',
                    'let' => ['clientId' => ['$toObjectId' => '$client_id']],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$_id', '$$clientId']]]]
                    ],
                    'as' => 'client'
                ]],
        
                // Lookup project_manager
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'project_manager_id',
                    'foreignField' => '_id',
                    'as' => 'project_manager'
                ]],
        
                // Lookup created_by
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'created_by',
                    'foreignField' => '_id',
                    'as' => 'created_bys'
                ]],

                [
                    '$lookup' => [
                        'from' => 'users',
                        'let' => [
                            'assigneeIds' => [
                                '$map' => [
                                    'input' => '$assignee',
                                    'as' => 'id',
                                    'in' => [ '$toObjectId' => '$$id' ]
                                ]
                            ]
                        ],
                        'pipeline' => [
                            [
                                '$match' => [
                                    '$expr' => [
                                        '$in' => ['$_id', '$$assigneeIds']
                                    ]
                                ]
                            ]
                        ],
                        'as' => 'assignee_details'
                    ]
                ],
                // Lookup timesheet for calculating spent hours
                ['$lookup' => [
                    'from' => 'timesheets',
                    'let' => ['projectIdStr' => ['$toString' => '$_id']], // Convert project ObjectId to string
                    'pipeline' => [
                        ['$match' => [
                            '$expr' => ['$eq' => ['$project_id', '$$projectIdStr']]
                        ]],
                        ['$unwind' => '$dates'],
                        ['$unwind' => '$dates.time_log'],
                        ['$addFields' => [
                            'start' => [
                                '$dateFromString' => [
                                    'dateString' => [
                                        '$concat' => ['$dates.date', 'T', '$dates.time_log.start_time', ':00']
                                    ],
                                    'format' => '%Y-%m-%dT%H:%M:%S'
                                ]
                            ],
                            'end' => [
                                '$dateFromString' => [
                                    'dateString' => [
                                        '$concat' => ['$dates.date', 'T', '$dates.time_log.end_time', ':00']
                                    ],
                                    'format' => '%Y-%m-%dT%H:%M:%S'
                                ]
                            ]
                        ]],
                        ['$addFields' => [
                            'diff_hours' => [
                                '$divide' => [
                                    ['$subtract' => ['$end', '$start']],
                                    1000 * 60 * 60 // convert milliseconds to hours
                                ]
                            ]
                        ]],
                        ['$group' => [
                            '_id' => null,
                            'total_hours' => ['$sum' => '$diff_hours']
                        ]]
                    ],
                    'as' => 'spent_time'
                ]],
        
                // Add spent_hours field to project
                ['$addFields' => [
                    'spent_hours' => [
                        '$round' => [
                            ['$ifNull' => [['$arrayElemAt' => ['$spent_time.total_hours', 0]], 0]],
                            2
                        ]
                    ]
                ]],
        
                // Sort by creation date
                ['$sort' => ['created_at' => -1]],
        
                // Project fields
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
                    'assignee_details' => 1,
                    'estimated_hours' => 1,
                    'project_manager_id' => 1,
                    'project_status_id' => 1,
                    'project_status' => 1,
                    'created_by' => 1,
                    'created_bys' => 1,                    
                    'platforms' => 1,
                    'languages' => 1,
                    'other_details' => 1,
                    'spent_hours' => 1
                ]]
            ]);
        });

        return response()->json($projects, 200);
    }

    public function summary(Request $request)
    {
        $matchStage = [];

        // Filter by client_id if provided
        if ($request->has('client_id')) {
            $matchStage['client_id'] = strval($request->client_id);
        }

        $summary = Project::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                // Filter by client_id
                ['$match' => $matchStage],

                // Group by client_id and project_status_id
                [
                    '$group' => [
                        '_id' => [
                            'client_id' => '$client_id',
                            'project_status_id' => '$project_status_id'
                        ],
                        'total_projects' => ['$sum' => 1]
                    ]
                ],

                // Reshape data to bring client_id as the top-level key
                [
                    '$group' => [
                        '_id' => '$_id.client_id',
                        'total_projects' => ['$sum' => '$total_projects'],
                        'statuses' => [
                            '$push' => [
                                'status_id' => [
                                    '$toObjectId' => '$_id.project_status_id' // Convert to ObjectId
                                ],
                                'total_projects' => '$total_projects'
                            ]
                        ]
                    ]
                ],

                // Unwind the statuses array
                ['$unwind' => '$statuses'],

                // Lookup project status details from project_statuses collection
                [
                    '$lookup' => [
                        'from' => 'project_statuses',
                        'localField' => 'statuses.status_id',
                        'foreignField' => '_id',
                        'as' => 'status_info'
                    ]
                ],

                // Unwind the status_info array (Correct syntax)
                ['$unwind' => ['path' => '$status_info', 'preserveNullAndEmptyArrays' => true]],

                // Restructure the statuses array with status_name
                [
                    '$group' => [
                        '_id' => '$_id',
                        'total_projects' => ['$first' => '$total_projects'],
                        'statuses' => [
                            '$push' => [
                                'status_id' => '$statuses.status_id',
                                'status_name' => ['$ifNull' => ['$status_info.name', 'Unknown']], // Handle missing status names
                                'total_projects' => '$statuses.total_projects'
                            ]
                        ]
                    ]
                ],

                // Format the final output
                [
                    '$project' => [
                        '_id' => 0,
                        'client_id' => '$_id',
                        'total_projects' => 1,
                        'statuses' => 1
                    ]
                ]
            ]);
        });

        return response()->json($summary, 200);
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
            'budget' => 'nullable|string',
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
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
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
            'budget' => 'nullable|string',
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
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
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
