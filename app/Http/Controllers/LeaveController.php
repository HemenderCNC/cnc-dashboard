<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\GeneralSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Holiday;
use App\Models\User;
use MongoDB\BSON\UTCDateTime;
use Illuminate\Support\Facades\Mail;
use App\Mail\LeaveRequestedMail;
use App\Mail\LeaveStatusMail;


class LeaveController extends Controller
{
    // Employee can view only their own leave requests
    public function index(Request $request)
    {
        $query = Leave::with(['employee:id,name,last_name']);

        // Pagination setup
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);

        // If user is an Employee, restrict to their own records
        if ($request->user->role->slug === 'employee') {
            $query->where('employee_id', $request->user->id);
        } else if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by date range (start_date, end_date)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        // Filter by status (Supports multiple statuses separated by comma)
        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);

            $query->where(function ($q) use ($statuses) {
                foreach ($statuses as $status) {
                    $q->orWhere('status', 'regex', new \MongoDB\BSON\Regex("^$status$", 'i'));
                }
            });
        }

        $query->orderBy('created_at', 'desc');

        if ($limit == -1) {
            $leaves = $query->get();

            return response()->json([
                'data' => $leaves,
                'meta' => [
                    'page' => 1,
                    'limit' => $limit,
                    'total' => $leaves->count(),
                    'total_pages' => 1,
                ]
            ], 200);
        }

        // Else use pagination
        $leaves = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $leaves->items(),
            'meta' => [
                'page' => $leaves->currentPage(),
                'limit' => $leaves->perPage(),
                'total' => $leaves->total(),
                'total_pages' => $leaves->lastPage(),
            ]
        ], 200);
    }

    public function getAllLeaves(Request $request)
    {
        $query = Leave::with(['employee:id,name,last_name']);

        // Pagination setup
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);


        // Filter by date range (start_date, end_date)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        // Filter by status (Supports multiple statuses separated by comma)
        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);

            $query->where(function ($q) use ($statuses) {
                foreach ($statuses as $status) {
                    $q->orWhere('status', 'regex', new \MongoDB\BSON\Regex("^$status$", 'i'));
                }
            });
        }

        $query->orderBy('created_at', 'desc');

        if ($limit == -1) {
            $leaves = $query->get();

            return response()->json([
                'data' => $leaves,
                'meta' => [
                    'page' => 1,
                    'limit' => $limit,
                    'total' => $leaves->count(),
                    'total_pages' => 1,
                ]
            ], 200);
        }

        // Else use pagination
        $leaves = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $leaves->items(),
            'meta' => [
                'page' => $leaves->currentPage(),
                'limit' => $leaves->perPage(),
                'total' => $leaves->total(),
                'total_pages' => $leaves->lastPage(),
            ]
        ], 200);

    }

    // Employee submits a leave request
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => [
                'required',
                'date',
                Rule::when($request->boolean('half_day'), ['same:end_date']),
            ],
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'nullable',
            'half_day_type' => 'nullable',
            'reason' => 'required|string',
            'leave_type' => 'nullable|string',
            'medical_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employeeId = $request->user->id;
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // 1. Check overlapping leave dates
        $overlap = Leave::where('employee_id', $employeeId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate->toDateString())
                            ->where('end_date', '>=', $endDate->toDateString());
                    });
            })
            ->exists();
        if ($overlap) {
            return response()->json([
                'message' => 'You have already applied for leave during these dates.'
            ], 422);
        }

        // 2. Calculate leave duration excluding weekends & holidays
        $leaveDuration = 0;
        if ($request->boolean('half_day')) {
            $leaveDuration = 0.5;
        } else {
            $holidayDates = Holiday::pluck('festival_date')->map(fn($d) => Carbon::parse($d)->toDateString())->toArray();

            $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->copy()->addDay());

            foreach ($period as $date) {
                $carbonDate = Carbon::instance($date);
                $day = $carbonDate->toDateString();
                $isWeekend = in_array($carbonDate->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
                $isHoliday = in_array($day, $holidayDates);

                if (!$isWeekend && !$isHoliday) {
                    $leaveDuration++;
                }
            }
        }

        if ($leaveDuration == 0) {
            return response()->json([
                'message' => 'This day is already holiday.'
            ], 422);
        }

        // Check if employee has enough leave balance
        $user = $request->user;
        if ($request->filled('leave_type')) {
            $hasEnoughBalance = true;
            if ($request->leave_type === 'Privilege Leave (PL)' && (float)$user->privilege_leave < $leaveDuration) {
                $hasEnoughBalance = false;
            } elseif ($request->leave_type === 'Paternity Leave' && (float)$user->paternity_leave < $leaveDuration) {
                $hasEnoughBalance = false;
            } elseif ($request->leave_type === 'Critical Medical Leave (CML)' && (float)$user->critical_medical_leave < $leaveDuration) {
                $hasEnoughBalance = false;
            }

            if (!$hasEnoughBalance) {
                return response()->json([
                    'message' => 'You do not have enough balance for this leave type.'
                ], 422);
            }
        }

        $medicalDocumentPath = null;
        if ($request->hasFile('medical_document')) {
            $medicalDocumentPath = $request->file('medical_document')->store('medical_documents', 'public');
        }

        $leave = Leave::create([
            'employee_id' => $employeeId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'leave_duration' => $leaveDuration,
            'half_day' => $request->boolean('half_day'),
            'half_day_type' => $request->boolean('half_day') ? $request->half_day_type : null,
            'reason' => $request->reason,
            'status' => 'pending',
            'leave_type' => $request->leave_type,
            'medical_document' => $medicalDocumentPath,
            'year' => date('Y')
        ]);

        // Deduct from the respective leave balance based on leave_type
        $user = $request->user;
        if ($request->filled('leave_type')) {
            if ($request->leave_type === 'Privilege Leave (PL)') {
                $user->privilege_leave = (float)$user->privilege_leave - $leaveDuration;
            } elseif ($request->leave_type === 'Paternity Leave') {
                $user->paternity_leave = (float)$user->paternity_leave - $leaveDuration;
            } elseif ($request->leave_type === 'Critical Medical Leave (CML)') {
                $user->critical_medical_leave = (float)$user->critical_medical_leave - $leaveDuration;
            } elseif ($request->leave_type === 'Leave Without Pay') {
                $user->leave_without_pay = (float)$user->leave_without_pay + $leaveDuration;
            }
            $user->save();
        }

        if (!in_array($request->user->email, ['vishva.cnc26@gmail.com', 'developeruser@gmail.com'])) {
            try {
                $reportingManager = $request->user->reportingManager;
                $users = $request->user->email;

                $ccList = [
                    'nagender@codeandcore.com',
                    'saurabhsoni.cnc@gmail.com',
                    'nikul@codeandcore.com'
                ];

                if ($reportingManager && !empty($reportingManager->email)) {
                    $ccList[] = $reportingManager->email;
                }

                if ($users) {
                    $ccList[] = $users;
                }

                $ccList = array_unique($ccList);

                Mail::to('hr@codeandcore.com')
                    ->cc($ccList)
                    ->send(new LeaveRequestedMail($leave, $request->user));
                    
            } catch (\Exception $e) {

                return response()->json([
                    'message' => 'Leave request created successfully, but email notification failed.',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

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
        $leave = Leave::where('id', $id)->first();

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'nullable',
            'half_day_type' => 'nullable',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $oldDuration = (float)$leave->leave_duration;
        $newDuration = 0;

        // 1. Check overlapping leave dates (excluding current leave)
        $overlap = Leave::where('employee_id', $leave->employee_id)
            ->where('id', '!=', $id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate->toDateString())
                            ->where('end_date', '>=', $endDate->toDateString());
                    });
            })
            ->exists();
        if ($overlap) {
            return response()->json([
                'message' => 'You have already applied for leave during these dates.'
            ], 422);
        }

        // 2. Calculate leave duration excluding weekends & holidays
        if ($request->boolean('half_day')) {
            $newDuration = 0.5;
        } else {
            $holidayDates = Holiday::pluck('festival_date')->map(fn($d) => Carbon::parse($d)->toDateString())->toArray();

            $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->copy()->addDay());

            foreach ($period as $date) {
                $carbonDate = Carbon::instance($date);
                $day = $carbonDate->toDateString();
                $isWeekend = in_array($carbonDate->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
                $isHoliday = in_array($day, $holidayDates);

                if (!$isWeekend && !$isHoliday) {
                    $newDuration++;
                }
            }
        }

        if ($newDuration == 0) {
            return response()->json([
                'message' => 'This day is already holiday.'
            ], 422);
        }

        // 3. Adjust leave balance
        $durationDiff = $newDuration - $oldDuration;
        $user = User::find($leave->employee_id);

        if ($user && $leave->leave_type && $durationDiff != 0 && in_array(strtolower($leave->status), ['pending', 'approved'])) {
            // Check if employee has enough leave balance for the increase
            if ($durationDiff > 0) {
                $hasEnoughBalance = true;
                if ($leave->leave_type === 'Privilege Leave (PL)' && (float)$user->privilege_leave < $durationDiff) {
                    $hasEnoughBalance = false;
                } elseif ($leave->leave_type === 'Paternity Leave' && (float)$user->paternity_leave < $durationDiff) {
                    $hasEnoughBalance = false;
                } elseif ($leave->leave_type === 'Critical Medical Leave (CML)' && (float)$user->critical_medical_leave < $durationDiff) {
                    $hasEnoughBalance = false;
                }

                if (!$hasEnoughBalance) {
                    return response()->json([
                        'message' => 'You do not have enough balance for this increase.'
                    ], 422);
                }
            }

            // Deduct or restore balance based on the difference
            if ($leave->leave_type === 'Privilege Leave (PL)') {
                $user->privilege_leave = (float)$user->privilege_leave - $durationDiff;
            } elseif ($leave->leave_type === 'Paternity Leave') {
                $user->paternity_leave = (float)$user->paternity_leave - $durationDiff;
            } elseif ($leave->leave_type === 'Critical Medical Leave (CML)') {
                $user->critical_medical_leave = (float)$user->critical_medical_leave - $durationDiff;
            } elseif ($leave->leave_type === 'Leave Without Pay') {
                $user->leave_without_pay = (float)$user->leave_without_pay + $durationDiff;
            }
            $user->save();
        }

        $leave->update([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'leave_duration' => $newDuration,
            'half_day' => $request->boolean('half_day'),
            'half_day_type' => $request->boolean('half_day') ? $request->half_day_type : null,
            'reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Leave request updated successfully', 'leave' => $leave], 200);
    }

    // Employee can cancel a leave request only if the leave start date has not passed
    public function cancel($id, Request $request)
    {
        $leave = Leave::where('id', $id)->first();

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if (in_array($leave->status, ['canceled', 'rejected'])) {
            return response()->json(['message' => 'Leave request is already ' . $leave->status], 404);
        }

        // Check if the leave start date has already passed
        $today = now()->toDateString();
        if ($leave->start_date < $today) {
            return response()->json(['message' => 'You cannot cancel a leave request after the start date has passed'], 403);
        }

        // Restore the respective leave balance based on leave_type
        $user = User::find($leave->employee_id);
        if ($user && $leave->leave_type) {
            $leaveDuration = (float)$leave->leave_duration;
            if ($leave->leave_type === 'Privilege Leave (PL)') {
                $user->privilege_leave = (float)$user->privilege_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Paternity Leave') {
                $user->paternity_leave = (float)$user->paternity_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Critical Medical Leave (CML)') {
                $user->critical_medical_leave = (float)$user->critical_medical_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Leave Without Pay') {
                $user->leave_without_pay = (float)$user->leave_without_pay - $leaveDuration;
            }
            $user->save();
        }

        $leave->update(['status' => 'canceled']);

        try {
            Mail::to($user->email)->send(new LeaveStatusMail($leave, $user, 'canceled', $request->user));
        } catch (\Exception $e) {
            // Log error or ignore
        }

        return response()->json(['message' => 'Leave request canceled successfully'], 200);
    }

    public function getLeaveSummary(Request $request)
    {
        $employeeId = $request->employee_id ?? $request->user->id;

        if ($request->user->role->slug === 'employee') {
            $employeeId = $request->user->id;
        }

        $user = User::find($employeeId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Leave summary retrieved successfully',
            'privilege_leave' => (float) $user->privilege_leave,
            'critical_medical_leave' => (float) $user->critical_medical_leave,
            'paternity_leave' => (float) $user->paternity_leave,
            'leave_without_pay' => (float) $user->leave_without_pay
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

        // Handle balance synchronization
        $user = User::find($leave->employee_id);
        if ($user) {
            $leaveDuration = (float)$leave->leave_duration;
            $oldStatus = strtolower($leave->status);
            $oldType = $leave->leave_type;
            $newType = $request->leave_type;

            // 1. If it was previously deducted (Pending or Approved), restore it first to reset
            if (in_array($oldStatus, ['pending', 'approved']) && $oldType) {
                if ($oldType === 'Privilege Leave (PL)') {
                    $user->privilege_leave = (float)$user->privilege_leave + $leaveDuration;
                } elseif ($oldType === 'Paternity Leave') {
                    $user->paternity_leave = (float)$user->paternity_leave + $leaveDuration;
                } elseif ($oldType === 'Critical Medical Leave (CML)') {
                    $user->critical_medical_leave = (float)$user->critical_medical_leave + $leaveDuration;
                } elseif ($oldType === 'Leave Without Pay') {
                    $user->leave_without_pay = (float)$user->leave_without_pay - $leaveDuration;
                }
            }

            // 2. Deduct for the new approved type
            if ($newType === 'Privilege Leave (PL)') {
                $user->privilege_leave = (float)$user->privilege_leave - $leaveDuration;
            } elseif ($newType === 'Paternity Leave') {
                $user->paternity_leave = (float)$user->paternity_leave - $leaveDuration;
            } elseif ($newType === 'Critical Medical Leave (CML)') {
                $user->critical_medical_leave = (float)$user->critical_medical_leave - $leaveDuration;
            } elseif ($newType === 'Leave Without Pay') {
                $user->leave_without_pay = (float)$user->leave_without_pay + $leaveDuration;
            }
            
            $user->save();
        }

        $leave->update([
            'status' => 'approved',
            'leave_type' => $request->leave_type,
            'approve_comment' => $request->approve_comment,
            'approved_by' => $request->user->id
        ]);

        try {
            Mail::to($user->email)->send(new LeaveStatusMail($leave, $user, 'approved', $request->user));
        } catch (\Exception $e) {
        }

        return response()->json(['message' => 'Leave approved successfully', 'leave' => $leave], 200);
    }

    // HR rejects a leave request
    public function reject(Request $request, $id)
    {
        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if (in_array($leave->status, ['rejected', 'canceled'])) {
            return response()->json(['message' => 'Leave request is already ' . $leave->status], 404);
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

        $user = User::find($leave->employee_id);
        if ($user && $leave->leave_type) {
            $leaveDuration = (float)$leave->leave_duration;
            if ($leave->leave_type === 'Privilege Leave (PL)') {
                $user->privilege_leave = (float)$user->privilege_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Paternity Leave') {
                $user->paternity_leave = (float)$user->paternity_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Critical Medical Leave (CML)') {
                $user->critical_medical_leave = (float)$user->critical_medical_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Leave Without Pay') {
                $user->leave_without_pay = (float)$user->leave_without_pay - $leaveDuration;
            }
            $user->save();
        }

        $leave->update([
            'status' => 'rejected',
            'approve_comment' => $request->approve_comment,
            'approved_by' => $request->user->id
        ]);

        try {
            Mail::to($user->email)->send(new LeaveStatusMail($leave, $user, 'rejected', $request->user));
        } catch (\Exception $e) {
            // Log error or ignore
        }

        return response()->json(['message' => 'Leave rejected', 'leave' => $leave], 200);
    }

    public function allUsersPendingLeaves(Request $request)
    {
        if ($request->user->role->slug !== 'employee') {
            $leaves = Leave::with(['employee:id,name,last_name'])
                ->where('status', 'pending')
                ->get();
        } else {
            return response()->json(['message' => 'You don\'t have permission to view these records.'], 422);
        }

        if (!$leaves) {
            return response()->json(['message' => 'No leaves found'], 404);
        }

        return response()->json(['message' => 'Leave rejected', 'leave' => $leaves], 200);
    }
    public function destroy(string $id)
    {
        $Leave = Leave::find($id);
        if (!$Leave) {
            return response()->json(['message' => 'Leave not found'], 404);
        }

        $Leave->delete();
        return response()->json(['message' => 'Leave deleted successfully'], 200);
    }
    public function changeLeaveBalance(Request $request)
    {
        $users = User::all();

        $privilege_leave = (float) $request->input('privilege_leave', 12);
        $paternity_leave = (float) $request->input('paternity_leave', 4);
        $critical_medical_leave = (float) $request->input('critical_medical_leave', 4);
        $leave_without_pay = (float) $request->input('leave_without_pay', 0);

        foreach ($users as $user) {
            $user->privilege_leave = $privilege_leave;
            $user->paternity_leave = $paternity_leave;
            $user->critical_medical_leave = $critical_medical_leave;
            $user->leave_without_pay = $leave_without_pay;
            $user->save();
        }

        return response()->json([
            'message' => 'Leave balances updated for all users successfully'
        ], 200);
    }
    public function getLeaveBalance(Request $request)
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        $query = User::query();

        if ($request->has('employee_id') && !empty($request->employee_id)) {
            $query->where('_id', $request->employee_id);
        }

        $users = $query->paginate((int)$limit, ['*'], 'page', (int)$page);

        $leaveBalances = [];

        foreach ($users as $user) {
            $leaveBalances[] = [
                'user_id' => $user->id,
                'employee_name' => trim($user->name . ' ' . $user->last_name),
                'privilege_leave' => (float) $user->privilege_leave,
                'paternity_leave' => (float) $user->paternity_leave,
                'critical_medical_leave' => (float) $user->critical_medical_leave,
                'leave_without_pay' => (float) $user->leave_without_pay
            ];
        }

        return response()->json([
            'message' => 'Leave balances fetched successfully',
            'data' => $leaveBalances,
            'meta' => [
                'total_records' => $users->total(),
                'total_pages' => $users->lastPage(),
                'current_page' => $users->currentPage(),
                'limit' => $users->perPage()
            ]
        ], 200);
    }
    public function UpdateuserLeaveBalance(Request $request)
    {
        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->privilege_leave = (float) $request->input('privilege_leave', 12);
        $user->paternity_leave = (float) $request->input('paternity_leave', 4);
        $user->critical_medical_leave = (float) $request->input('critical_medical_leave', 4);
        $user->leave_without_pay = (float) $request->input('leave_without_pay', 0);
        $user->save();

        return response()->json([
            'message' => 'User leave balance updated successfully',
            'data' => $user
        ], 200);
    }
}
