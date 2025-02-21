<?php

namespace App\Http\Controllers;
use App\Models\Leave;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class ManagementLeaveController extends Controller
{
    // HR/Management views all leave requests
    public function index()
    {
        return response()->json(Leave::all(), 200);
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

    //HR get details of a leave by leave ID
    public function show($id)
    {
        $leave = Leave::find($id);
    
        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }
    
        return response()->json([
            'message' => 'Leave request details',
            'leave' => $leave
        ], 200);
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

    // // HR views leave history
    // public function history()
    // {
    //     $history = Leave::whereIn('status', ['approved', 'rejected', 'canceled'])->get();
    //     return response()->json($history, 200);
    // }
}
