<?php

namespace App\Http\Controllers;
use App\Models\Leave;
use App\Models\GeneralSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    // Employee can view only their own leave requests
    public function index(Request $request)
    {
        $query = Leave::query();

        // If user is an Employee, restrict to their own records
        if ($request->user->role->name === 'Employee') {
            $query->where('employee_id', $request->user->id);
        }
        else if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
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

    // Employee submits a leave request
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => [
                'required',
                'date',
                Rule::when($request->half_day, ['same:end_date']), // Ensures same start and end date if half-day is true
            ],
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'boolean',
            'half_day_type' => 'nullable|in:first_half,second_half',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $leave_duration = (new \DateTime($request->start_date))->diff(new \DateTime($request->end_date))->days + 1;
        if($request->half_day){
            $leave_duration = 0.5;
        }
        $leave = Leave::create([
            'employee_id' => $request->user->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'leave_duration' => $leave_duration,
            'half_day' => $request->half_day ?? false,
            'half_day_type' => $request->half_day ? $request->half_day_type : null,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json($leave, 201);
    }

    // Employee views a specific leave request
    public function show($id, Request $request)
    {
        $leave = Leave::where('id', $id)->where('employee_id', $request->user->id)->first();

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        return response()->json($leave, 200);
    }


    // Employee can update their leave request **only if status is pending**
    public function update(Request $request, $id)
    {
        $leave = Leave::where('id', $id)->where('employee_id', $request->user->id)->first();

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'You can only update a pending leave request'], 403);
        }

        if ($leave->status === 'canceled') {
            return response()->json(['message' => 'You cannot update a canceled leave request'], 403);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'boolean',
            'half_day_type' => 'nullable|in:first_half,second_half',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $leave_duration = (new \DateTime($request->start_date))->diff(new \DateTime($request->end_date))->days + 1;
        if($request->half_day){
            $leave_duration = 0.5;
        }
        $leave->update([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'leave_duration' => $leave_duration,
            'half_day' => $request->half_day ?? false,
            'half_day_type' => $request->half_day ? $request->half_day_type : null,
            'reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Leave request updated successfully', 'leave' => $leave], 200);
    }

    // Employee can cancel a leave request only if the leave start date has not passed
    public function cancel($id, Request $request)
    {
        $leave = Leave::where('id', $id)->where('employee_id', $request->user->id)->first();

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'You can only cancel a leave request that is pending'], 403);
        }

        // Check if the leave start date has already passed
        $today = now()->toDateString();
        if ($leave->start_date < $today) {
            return response()->json(['message' => 'You cannot cancel a leave request after the start date has passed'], 403);
        }

        $leave->update(['status' => 'canceled']);

        return response()->json(['message' => 'Leave request canceled successfully'], 200);
    }

    public function getLeaveSummary(Request $request)
    {
        $query = Leave::query();

        // If user is an Employee, restrict to their own records
        if ($request->user->role->name === 'Employee') {
            $query->where('employee_id', $request->user->id);
        }
        else if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        $totalLeaves = 12; // Define the total allowed leaves
        $settings = GeneralSettings::firstOrNew([]);
        $leave_settings = $settings->leave_settings ?? [];
        if(!empty($leave_settings)){
            $totalLeaves = $leave_settings['total_leaves_per_person'];
        }
        // Get the total approved leave days
        $consumedLeaves = $query->where('status', 'approved')
            ->sum('leave_duration'); //leave duration

        // Calculate remaining leaves
        $remainingLeaves = max($totalLeaves - $consumedLeaves, 0); // Ensures no negative values

        return response()->json([
            'message' => 'Leave summary retrieved successfully',
            'total_leaves' => $totalLeaves,
            'consumed_leaves' => $consumedLeaves,
            'remaining_leaves' => $remainingLeaves
        ], 200);
    }

    // HR approves a leave request
    public function approve(Request $request, $id)
    {
        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|string',
            'approve_comment' => 'nullable|string',
            // 'approved_by' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $leave->update([
            'status' => 'approved',
            'leave_type' => $request->leave_type,
            'approve_comment' => $request->approve_comment,
            'approved_by' => $request->user->id
        ]);

        return response()->json(['message' => 'Leave approved successfully', 'leave' => $leave], 200);
    }

    // HR rejects a leave request
    public function reject(Request $request, $id)
    {
        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'approve_comment' => 'required|string',
            // 'approved_by' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $leave->update([
            'status' => 'rejected',
            'approve_comment' => $request->approve_comment,
            'approved_by' => $request->user->id
        ]);

        return response()->json(['message' => 'Leave rejected', 'leave' => $leave], 200);
    }
}
