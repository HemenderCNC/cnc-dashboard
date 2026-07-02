<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveBalance;
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
        $query = Leave::with(['employee:_id,name,last_name,profile_photo']);

        // Pagination setup
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);

        // Role-based leave restrictions
        if ($request->user->role->slug === 'employee') {
            $query->where('employee_id', $request->user->id);
        } else if ($request->user->role->slug === 'team-leader') {
            $childEmployeeIds = User::where('reporting_manager_id', (string)$request->user->id)->pluck('id')->toArray();
            $allowedEmployeeIds = array_merge([$request->user->id], $childEmployeeIds);

            if ($request->filled('employee_id') && in_array($request->employee_id, $allowedEmployeeIds)) {
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->whereIn('employee_id', $allowedEmployeeIds);
            }
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
        $today = Carbon::today()->toDateString();
        $leaves = Leave::with(['employee:_id,name,last_name,profile_photo'])
            ->where('status', 'approved')
            ->where('start_date', '>=', $today)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $leaves,
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
            ->whereNotIn('status', ['rejected', 'canceled', 'cancelled'])
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
        $userId = $request->user->id;
        $currentYear = date('Y');
        $leaveBalance = LeaveBalance::firstOrCreate(
            ['user_id' => (string) $userId, 'year' => (int) $currentYear],
            [
                'privilege_leave' => 0,
                'paternity_leave' => 0,
                'critical_medical_leave' => 0,
                'leave_without_pay' => 0,
            ]
        );

        if ($request->filled('leave_type')) {
            $hasEnoughBalance = true;
            if ($request->leave_type === 'Privilege Leave (PL)' && (float)$leaveBalance->privilege_leave < $leaveDuration) {
                $hasEnoughBalance = false;
            } elseif ($request->leave_type === 'Paternity Leave' && (float)$leaveBalance->paternity_leave < $leaveDuration) {
                $hasEnoughBalance = false;
            } elseif ($request->leave_type === 'Critical Medical Leave (CML)' && (float)$leaveBalance->critical_medical_leave < $leaveDuration) {
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
            $file = $request->file('medical_document');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            if (!is_dir(base_path('medical_documents'))) {
                mkdir(base_path('medical_documents'), 0755, true);
            }
            $file->move(base_path('medical_documents'), $fileName);
            $medicalDocumentPath = asset('medical_documents/' . $fileName);
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
        if ($request->filled('leave_type')) {
            if ($request->leave_type === 'Privilege Leave (PL)') {
                $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave - $leaveDuration;
            } elseif ($request->leave_type === 'Paternity Leave') {
                $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave - $leaveDuration;
            } elseif ($request->leave_type === 'Critical Medical Leave (CML)') {
                $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave - $leaveDuration;
            } elseif ($request->leave_type === 'Leave Without Pay') {
                $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay + $leaveDuration;
            }
            $leaveBalance->save();
        }

        
            try {
                $reportingManager = $request->user->reportingManager;
                $users = $request->user->email;

                $ccList = [
                    'patelparth5133@gmail.com'
                ];

                if ($reportingManager && !empty($reportingManager->email)) {
                    $ccList[] = $reportingManager->email;
                }

                if ($users) {
                    $ccList[] = $users;
                }

                $ccList = array_unique($ccList);

                Mail::to('patelparth56653@gmail.com')
                    ->cc($ccList)
                    ->send(new LeaveRequestedMail($leave, $request->user));
                    
            } catch (\Exception $e) {

                return response()->json([
                    'message' => 'Leave request created successfully, but email notification failed.',
                    'error' => $e->getMessage()
                ], 500);
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
            'medical_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
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
            ->whereNotIn('status', ['rejected', 'canceled', 'cancelled'])
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
        $leaveYear = $leave->year ?? date('Y');
        $leaveBalance = LeaveBalance::firstOrCreate(
            ['user_id' => (string) $leave->employee_id, 'year' => (int) $leaveYear],
            [
                'privilege_leave' => 0,
                'paternity_leave' => 0,
                'critical_medical_leave' => 0,
                'leave_without_pay' => 0,
            ]
        );

        if ($leave->leave_type && $durationDiff != 0 && in_array(strtolower($leave->status), ['pending', 'approved'])) {
            // Check if employee has enough leave balance for the increase
            if ($durationDiff > 0) {
                $hasEnoughBalance = true;
                if ($leave->leave_type === 'Privilege Leave (PL)' && (float)$leaveBalance->privilege_leave < $durationDiff) {
                    $hasEnoughBalance = false;
                } elseif ($leave->leave_type === 'Paternity Leave' && (float)$leaveBalance->paternity_leave < $durationDiff) {
                    $hasEnoughBalance = false;
                } elseif ($leave->leave_type === 'Critical Medical Leave (CML)' && (float)$leaveBalance->critical_medical_leave < $durationDiff) {
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
                $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave - $durationDiff;
            } elseif ($leave->leave_type === 'Paternity Leave') {
                $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave - $durationDiff;
            } elseif ($leave->leave_type === 'Critical Medical Leave (CML)') {
                $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave - $durationDiff;
            } elseif ($leave->leave_type === 'Leave Without Pay') {
                $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay + $durationDiff;
            }
            $leaveBalance->save();
        }

        $medicalDocumentPath = $leave->medical_document;
        if ($request->hasFile('medical_document')) {
            $file = $request->file('medical_document');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            if (!is_dir(base_path('medical_documents'))) {
                mkdir(base_path('medical_documents'), 0755, true);
            }
            $file->move(base_path('medical_documents'), $fileName);
            $medicalDocumentPath = asset('medical_documents/' . $fileName);
        }

        $leave->update([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'leave_duration' => $newDuration,
            'half_day' => $request->boolean('half_day'),
            'half_day_type' => $request->boolean('half_day') ? $request->half_day_type : null,
            'reason' => $request->reason,
            'medical_document' => $medicalDocumentPath,
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
        // $today = now()->toDateString();
        // if ($leave->start_date < $today) {
        //     return response()->json(['message' => 'You cannot cancel a leave request after the start date has passed'], 403);
        // }

        // Restore the respective leave balance based on leave_type
        $user = User::find($leave->employee_id);
        $leaveYear = $leave->year ?? date('Y');
        $leaveBalance = LeaveBalance::where('user_id', (string) $leave->employee_id)
            ->where('year', (int) $leaveYear)
            ->first();
        if ($leaveBalance && $leave->leave_type) {
            $leaveDuration = (float)$leave->leave_duration;
            if ($leave->leave_type === 'Privilege Leave (PL)') {
                $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Paternity Leave') {
                $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Critical Medical Leave (CML)') {
                $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Leave Without Pay') {
                $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay - $leaveDuration;
            }
            $leaveBalance->save();
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
        } else if ($request->user->role->slug === 'team-leader') {
            $childEmployeeIds = User::where('reporting_manager_id', (string)$request->user->id)->pluck('id')->toArray();
            $allowedEmployeeIds = array_merge([$request->user->id], $childEmployeeIds);

            if ($request->filled('employee_id') && in_array($request->employee_id, $allowedEmployeeIds)) {
                $employeeId = $request->employee_id;
            } else {
                $employeeId = $request->user->id;
            }
        }

        $user = User::find($employeeId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $currentYear = date('Y');
        $leaveBalance = LeaveBalance::firstOrCreate(
            ['user_id' => (string) $employeeId, 'year' => (int) $currentYear],
            [
                'privilege_leave' => 0,
                'paternity_leave' => 0,
                'critical_medical_leave' => 0,
                'leave_without_pay' => 0,
            ]
        );

        return response()->json([
            'message' => 'Leave summary retrieved successfully',
            'privilege_leave' => (float) $leaveBalance->privilege_leave,
            'critical_medical_leave' => (float) $leaveBalance->critical_medical_leave,
            'paternity_leave' => (float) $leaveBalance->paternity_leave,
            'leave_without_pay' => (float) $leaveBalance->leave_without_pay
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
        $leaveYear = $leave->year ?? date('Y');
        $leaveBalance = LeaveBalance::firstOrCreate(
            ['user_id' => (string) $leave->employee_id, 'year' => (int) $leaveYear],
            [
                'privilege_leave' => 0,
                'paternity_leave' => 0,
                'critical_medical_leave' => 0,
                'leave_without_pay' => 0,
            ]
        );

        if ($leaveBalance) {
            $leaveDuration = (float)$leave->leave_duration;
            $oldStatus = strtolower($leave->status);
            $oldType = $leave->leave_type;
            $newType = $request->leave_type;

            // 1. If it was previously deducted (Pending or Approved), restore it first to reset
            if (in_array($oldStatus, ['pending', 'approved']) && $oldType) {
                if ($oldType === 'Privilege Leave (PL)') {
                    $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave + $leaveDuration;
                } elseif ($oldType === 'Paternity Leave') {
                    $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave + $leaveDuration;
                } elseif ($oldType === 'Critical Medical Leave (CML)') {
                    $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave + $leaveDuration;
                } elseif ($oldType === 'Leave Without Pay') {
                    $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay - $leaveDuration;
                }
            }

            // 2. Deduct for the new approved type
            if ($newType === 'Privilege Leave (PL)') {
                $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave - $leaveDuration;
            } elseif ($newType === 'Paternity Leave') {
                $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave - $leaveDuration;
            } elseif ($newType === 'Critical Medical Leave (CML)') {
                $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave - $leaveDuration;
            } elseif ($newType === 'Leave Without Pay') {
                $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay + $leaveDuration;
            }
            
            $leaveBalance->save();
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
        $leaveYear = $leave->year ?? date('Y');
        $leaveBalance = LeaveBalance::where('user_id', (string) $leave->employee_id)
            ->where('year', (int) $leaveYear)
            ->first();
        if ($leaveBalance && $leave->leave_type) {
            $leaveDuration = (float)$leave->leave_duration;
            if ($leave->leave_type === 'Privilege Leave (PL)') {
                $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Paternity Leave') {
                $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Critical Medical Leave (CML)') {
                $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave + $leaveDuration;
            } elseif ($leave->leave_type === 'Leave Without Pay') {
                $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay - $leaveDuration;
            }
            $leaveBalance->save();
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
        if ($request->user->role->slug === 'team-leader') {
            $childEmployeeIds = User::where('reporting_manager_id', (string)$request->user->id)->pluck('id')->toArray();
            $leaves = Leave::with(['employee:_id,name,last_name'])
                ->where('status', 'pending')
                ->whereIn('employee_id', $childEmployeeIds)
                ->get();
        } else if ($request->user->role->slug !== 'employee') {
            $leaves = Leave::with(['employee:_id,name,last_name'])
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

        // Restore the respective leave balance if the status is pending or approved
        if (in_array(strtolower($Leave->status), ['pending', 'approved'])) {
            $leaveYear = $Leave->year ?? date('Y');
            $leaveBalance = LeaveBalance::where('user_id', (string) $Leave->employee_id)
                ->where('year', (int) $leaveYear)
                ->first();
            if ($leaveBalance && $Leave->leave_type) {
                $leaveDuration = (float)$Leave->leave_duration;
                if ($Leave->leave_type === 'Privilege Leave (PL)') {
                    $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave + $leaveDuration;
                } elseif ($Leave->leave_type === 'Paternity Leave') {
                    $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave + $leaveDuration;
                } elseif ($Leave->leave_type === 'Critical Medical Leave (CML)') {
                    $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave + $leaveDuration;
                } elseif ($Leave->leave_type === 'Leave Without Pay') {
                    $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay - $leaveDuration;
                }
                $leaveBalance->save();
            }
        }

        $Leave->delete();
        return response()->json(['message' => 'Leave deleted successfully'], 200);
    }

    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ids = $request->ids;
        $leaves = Leave::whereIn('id', $ids)->get();

        if ($leaves->isEmpty()) {
            return response()->json(['message' => 'No leaves found to delete'], 404);
        }

        $deletedCount = 0;
        foreach ($leaves as $leave) {
            // Restore the respective leave balance if the status is pending or approved
            if (in_array(strtolower($leave->status), ['pending', 'approved'])) {
                $leaveYear = $leave->year ?? date('Y');
                $leaveBalance = LeaveBalance::where('user_id', (string) $leave->employee_id)
                    ->where('year', (int) $leaveYear)
                    ->first();
                if ($leaveBalance && $leave->leave_type) {
                    $leaveDuration = (float)$leave->leave_duration;
                    if ($leave->leave_type === 'Privilege Leave (PL)') {
                        $leaveBalance->privilege_leave = (float)$leaveBalance->privilege_leave + $leaveDuration;
                    } elseif ($leave->leave_type === 'Paternity Leave') {
                        $leaveBalance->paternity_leave = (float)$leaveBalance->paternity_leave + $leaveDuration;
                    } elseif ($leave->leave_type === 'Critical Medical Leave (CML)') {
                        $leaveBalance->critical_medical_leave = (float)$leaveBalance->critical_medical_leave + $leaveDuration;
                    } elseif ($leave->leave_type === 'Leave Without Pay') {
                        $leaveBalance->leave_without_pay = (float)$leaveBalance->leave_without_pay - $leaveDuration;
                    }
                    $leaveBalance->save();
                }
            }
            $leave->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => 'Leaves deleted successfully',
            'deleted_count' => $deletedCount
        ], 200);
    }

    public function getLeaveBalance(Request $request)
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $currentYear = date('Y');

        $query = User::query();

        if ($request->user->role->slug === 'team-leader') {
            $childEmployeeIds = User::where('reporting_manager_id', (string)$request->user->id)->pluck('id')->toArray();
            $allowedEmployeeIds = array_merge([$request->user->id], $childEmployeeIds);

            if ($request->has('employee_id') && !empty($request->employee_id) && in_array($request->employee_id, $allowedEmployeeIds)) {
                $query->where('_id', $request->employee_id);
            } else {
                $query->whereIn('_id', $allowedEmployeeIds);
            }
        } else {
            if ($request->has('employee_id') && !empty($request->employee_id)) {
                $query->where('_id', $request->employee_id);
            }
        }

        $users = $query->paginate((int)$limit, ['*'], 'page', (int)$page);

        $leaveBalances = [];

        foreach ($users as $user) {
            $balance = LeaveBalance::where(
                ['user_id' => $user->_id, 'year' => (int) $currentYear]
            )->first();

            $leaveBalances[] = [
                'user_id' => $user->id,
                'employee_name' => trim($user->name . ' ' . $user->last_name),
                'privilege_leave' => $balance ? (float) ($balance->privilege_leave ?? 0) : 0,
                'paternity_leave' => $balance ? (float) ($balance->paternity_leave ?? 0) : 0,
                'critical_medical_leave' => $balance ? (float) ($balance->critical_medical_leave ?? 0) : 0,
                'leave_without_pay' => $balance ? (float) ($balance->leave_without_pay ?? 0) : 0
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
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'privilege_leave' => 'nullable|numeric|min:0',
            'paternity_leave' => 'nullable|numeric|min:0',
            'critical_medical_leave' => 'nullable|numeric|min:0',
            'leave_without_pay' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $currentYear = date('Y');
        $leaveBalance = LeaveBalance::updateOrCreate(
            [
                'user_id' => (string) $request->user_id,
                'year' => (int) $currentYear
            ],
            [
                'privilege_leave' => (float) $request->input('privilege_leave', 12),
                'paternity_leave' => (float) $request->input('paternity_leave', 4),
                'critical_medical_leave' => (float) $request->input('critical_medical_leave', 4),
                'leave_without_pay' => (float) $request->input('leave_without_pay', 0),
            ]
        );

        return response()->json([
            'message' => 'User leave balance updated successfully',
            'data' => $leaveBalance
        ], 200);
    }
}
