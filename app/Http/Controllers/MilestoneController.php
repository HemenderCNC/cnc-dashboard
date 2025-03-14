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
        $query = Milestones::query();

        // If user is an Employee, restrict to their own records
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by date range (start_date, end_date)
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Order and fetch results
        $leaves = $query->orderBy('created_at', 'desc')->get();

        return response()->json($leaves, 200);
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

        $milestones = Milestones::create([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'color' => $request->color,
            'project_id' => $request->project_id,
            'status' => $request->status,
            'created_by' => $request->user->id
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


        $milestone->update($request->only(['name', 'start_date', 'end_date', 'color', 'project_id', 'status']));

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
}
