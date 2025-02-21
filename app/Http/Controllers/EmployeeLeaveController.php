<?php

namespace App\Http\Controllers;
use App\Models\Leave;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class EmployeeLeaveController extends Controller
{
    // Employee can view only their own leave requests
    public function index(Request $request)
    {
        $employeeId = $request->user->id; // Get authenticated user ID
        $leaves = Leave::where('employee_id', $employeeId)->get();
        return response()->json($leaves, 200);
    }

    // Employee submits a leave request
    public function store(Request $request)
    {
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
}
