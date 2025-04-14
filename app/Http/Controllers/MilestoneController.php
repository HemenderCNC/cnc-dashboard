<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use App\Models\Milestones;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    // Get all Milestones
    public function index(Request $request)
{
    $matchStage = (object)[];

    if ($request->has('project_id')) {
        $matchStage->project_id = $request->project_id;
    }

    if ($request->has('status')) {
        $matchStage->status = $request->status;
    }

    if ($request->has('start_date')) {
        $startDate = new UTCDateTime(Carbon::parse($request->start_date)->getTimestampMs());
        if (!isset($matchStage->start_date)) {
            $matchStage->start_date = (object)[];
        }
        $matchStage->start_date->{'$gte'} = $startDate;
    }

    if ($request->has('end_date')) {
        $endDate = new UTCDateTime(Carbon::parse($request->end_date)->getTimestampMs());
        if (!isset($matchStage->end_date)) {
            $matchStage->end_date = (object)[];
        }
        $matchStage->end_date->{'$lte'} = $endDate;
    }

    $milestonesPipeline = [
        ['$match' => $matchStage],

        // ✅ Corrected $lookup
        [
            '$lookup' => [
                'from' => 'tasks',
                'let' => ['milestoneId' => '$_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$eq' => [
                                    ['$toObjectId' => '$milestone_id'],
                                    '$$milestoneId'
                                ]
                            ]
                        ]
                    ],
                    // Join task_statuses
                    [
                        '$lookup' => [
                            'from' => 'task_statuses',
                            'let' => ['statusId' => '$status_id'],
                            'pipeline' => [
                                [
                                    '$match' => [
                                        '$expr' => [
                                            '$eq' => [
                                                '$_id',
                                                ['$toObjectId' => '$$statusId']
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'as' => 'status_data'
                        ]
                    ],
                    // Flatten the status_data array
                    [
                        '$unwind' => [
                            'path' => '$status_data',
                            'preserveNullAndEmptyArrays' => true
                        ]
                    ]
                ],
                'as' => 'tasks'
            ]
        ],

        [
            '$project' => [
                'name' => 1,
                'start_date' => 1,
                'end_date' => 1,
                'color' => 1,
                'project_id' => 1,
                'status' => 1,
                'created_by' => 1,
                'order' => 1,
                'tasks' => 1,
                'total_tasks' => ['$size' => '$tasks'],
                'pending_tasks' => [
                    '$size' => [
                        '$filter' => [
                            'input' => '$tasks',
                            'as' => 'task',
                            'cond' => [
                                '$eq' => ['$$task.status_data.name', 'pending']
                            ]
                        ]
                    ]
                ]
            ]
        ],

        ['$sort' => ['order' => 1]]
    ];

    $milestones = Milestones::raw(function ($collection) use ($milestonesPipeline) {
        return $collection->aggregate($milestonesPipeline);
    });

    return response()->json($milestones, 200);
}


    // Store a new Milestones
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'project_id' => 'required|exists:projects,_id',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        // Find the highest order number for the project
        $maxOrder = Milestones::where('project_id', $request->project_id)->max('order');
        $nextOrder = $maxOrder !== null ? $maxOrder + 1 : 1;

        $milestones = Milestones::create([
            'name' => strtolower(trim($request->name)),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'color' => $request->color,
            'project_id' => $request->project_id,
            'status' => $request->status,
            'created_by' => $request->user->id,
            'order' => $nextOrder
        ]);

        return response()->json(['message' => 'Milestone created successfully', 'data' => $milestones], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $milestone = Milestones::findOrFail($id);
        return response()->json($milestone);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $milestone = Milestones::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'project_id' => 'required|exists:projects,_id',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }


        $data = $request->only(['name', 'start_date', 'end_date', 'color', 'project_id', 'status']);

        // Apply trim and lowercase to 'name'
        if (isset($data['name'])) {
            $data['name'] = strtolower(trim($data['name']));
        }

        $milestone->update($data);

        return response()->json(['message' => 'Milestone updated successfully', 'data' => $milestone]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $milestone = Milestones::findOrFail($id);

        // Check if the milestone has an associated image
        $milestone->delete();

        return response()->json(['message' => 'Milestone deleted successfully']);
    }


    /**
     * Set Milestone order
     */
    public function updateMilestoneOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,_id',
            'milestones' => 'required|array',
            'milestones.*.id' => 'required|exists:milestones,_id',
            'milestones.*.order' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        foreach ($request->milestones as $milestoneData) {
            Milestones::where('_id', $milestoneData['id'])
                ->where('project_id', $request->project_id)
                ->update(['order' => $milestoneData['order']]);

            // Add to response list
            $updatedMilestones[] = [
                'id' => $milestoneData['id'],
                'order' => $milestoneData['order']
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Milestone order updated successfully',
            'project_id' => $request->project_id,
            'updated_milestones' => $updatedMilestones
        ], 200);
    }

}
