<?php

namespace App\Http\Controllers;
use App\Models\Notice;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Overtime;
use App\Models\PredefinedNote;

class OvertimeController extends Controller
{
    public function otClaimStore(Request $request)
    {

    $validator = Validator::make($request->all(), [
        'date' => 'required',
        'task_id' => 'required',
        'shift_hours' => 'required',
        'working_hours' => 'required',
        'ot_hours' => 'required',
        'reason' => 'required',
        'url' => 'nullable',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
        ], 422);
    }

    $exists = Overtime::where('employee_id', $request->user->id)
        ->where('date', $request->date)
        ->where('task_id', $request->task_id)
        ->exists();

    if ($exists) {
        return response()->json([
            'message' => 'You have already applied OT with this task.',
        ], 422);
    }

    $working_minutes = $this->convertToMinutes($request->working_hours);
    $shift_minutes = $this->convertToMinutes($request->shift_hours);

    $max_ot_allowed = max(0, $working_minutes - $shift_minutes);

    $new_ot_minutes = $this->convertToMinutes($request->ot_hours);
    if ($new_ot_minutes <= 0) {
        return response()->json([
            'message' => 'OT hours must be greater than 00:00.',
        ], 422);
    }

    $existing_ot_sum = Overtime::where('employee_id', $request->user->id)
        ->where('date', $request->date)
        ->get()
        ->sum(function ($item) {
            return $this->convertToMinutes($item->ot_hours);
        });

    if ($existing_ot_sum >= $max_ot_allowed) {
        return response()->json([
            'message' => 'You have already claimed the maximum OT for this date.',
        ], 422);
    }

    if (($existing_ot_sum + $new_ot_minutes) > $max_ot_allowed) {
        $remaining = $max_ot_allowed - $existing_ot_sum;
        $remaining = max(0, $remaining);

        return response()->json([
            'message' => 'You can only claim remaining OT: ' . $this->convertMinutesToTime($remaining),
        ], 422);
    }

    $otClaim = new Overtime();
    $otClaim->employee_id = $request->user->id;
    $otClaim->date = $request->date;
    $otClaim->task_id = $request->task_id;
    $otClaim->shift_hours = $request->shift_hours;
    $otClaim->working_hours = $request->working_hours;
    $otClaim->ot_hours = $request->ot_hours;
    $otClaim->reason = $request->reason;
    $otClaim->url = $request->url;
    $otClaim->status = 'Pending';
    $otClaim->save();

    return response()->json([
        'message' => 'OT claim created successfully',
    ], 200);

    }

    public function otUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'approved_ot_hours' => 'nullable',
            'review_note' => 'nullable',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $otClaim = Overtime::find($request->id);

        if (!$request->has('approved_ot_hours')) {
            $otClaim->approved_ot_hours = $otClaim->ot_hours;
        } else {
            $otClaim->approved_ot_hours = $request->approved_ot_hours;
        }

        $otClaim->review_note = $request->review_note;
        $otClaim->status = $request->status;
        $otClaim->updated_by = $request->user->id;  
        $otClaim->updated_at = Carbon::now();
        $otClaim->save();

        return response()->json([
            'message' => 'OT claim updated successfully',
        ], 200);
    }

    public function otClaimList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required', // format: 2026-03
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $otClaim = Overtime::with(['employee', 'task'])->where('date', 'like', $request->month . '%')->get();

        $grouped = $otClaim->groupBy('employee_id');

        $result = $grouped->map(function ($items, $employeeId) {

            $total_ot_mins = $items->sum(function ($item) {
                return $this->convertToMinutes($item->ot_hours);
            });

            $approved_mins = $items->filter(function($item) {
                return in_array($item->status, ['Approved', 'Partial Approved']);
            })->sum(function ($item) {
                return $this->convertToMinutes($item->approved_ot_hours);
            });

            $employee = $items->first()->employee ?? null;

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee ? $employee->name . ' ' . ($employee->last_name ?? '') : 'Unknown',
                'total_ot_hours' => $this->convertMinutesToTime($total_ot_mins),
                'days_ot_claimed' => $items->count(),
                'hrs_approved' => $this->convertMinutesToTime($approved_mins),
                'ot_claim' => $items->values(),
            ];
        })->values();

        return response()->json([
            'message' => 'OT claim list',
            'data' => $result,
        ], 200);
    }

    private function convertToMinutes($time)
    {
        if (!$time) return 0;
        if (strpos($time, ':') !== false) {
            list($hours, $minutes) = explode(':', $time);
            return ((int)$hours * 60) + (int)$minutes;
        }
        return (int)$time * 60;
    }

    private function convertMinutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
   
   public function otApprove(Request $request)
   {

    $validator = Validator::make($request->all(), [
        'id' => 'required',
        'status' => 'required',
        'review_note' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
        ], 422);
    }

    $otClaim = Overtime::find($request->id);
    $otClaim->status = $request->status;
    $otClaim->review_note = $request->review_note;
    $otClaim->updated_by = $request->user->id;
    $otClaim->updated_at = Carbon::now();

    if (!$request->has('approved_ot_hours')) {
        $otClaim->approved_ot_hours = $otClaim->ot_hours;
    } else {
        $otClaim->approved_ot_hours = $request->approved_ot_hours;
    }

    $otClaim->save();

    return response()->json([
        'message' => 'OT claim approved successfully',
    ], 200);
    
   }

   public function otReject(Request $request)
   {

    $validator = Validator::make($request->all(), [
        'id' => 'required',
        'status' => 'required',
        'review_note' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
        ], 422);
    }

    $otClaim = Overtime::find($request->id);
    $otClaim->status = $request->status;
    $otClaim->review_note = $request->review_note;
    $otClaim->updated_by = $request->user->id;
    $otClaim->updated_at = Carbon::now();

    if (!$request->has('approved_ot_hours')) {
        $otClaim->approved_ot_hours = $otClaim->ot_hours;
    } else {
        $otClaim->approved_ot_hours = $request->approved_ot_hours;
    }

    $otClaim->save();

    return response()->json([
        'message' => 'OT claim rejected successfully',
    ], 200);
    
   }

   public function otPartialApprove(Request $request)
   {

    $validator = Validator::make($request->all(), [
        'id' => 'required',
        'status' => 'required',
        'review_note' => 'required',
        'approved_ot_hours' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
        ], 422);
    }

    $otClaim = Overtime::find($request->id);
    $otClaim->status = $request->status;
    $otClaim->review_note = $request->review_note;
    $otClaim->updated_by = $request->user->id;
    $otClaim->updated_at = Carbon::now();
    $otClaim->approved_ot_hours = $request->approved_ot_hours;
    $otClaim->save();

    return response()->json([
        'message' => 'OT claim approved successfully',
    ], 200);
    
   }

   public function preDefiendReviewNotes(Request $request)
   {

    $predefined_notes = PredefinedNote::all();
    
    return response()->json([
        'message' => 'Predefined notes',
        'data' => $predefined_notes,
    ], 200);
    
   }

   public function preDefiendReviewNotesStore(Request $request)
   {

    $validator = Validator::make($request->all(), [
        'note' => 'required|max:25',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
        ], 422);
    }

    $predefined_note = new PredefinedNote();
    $predefined_note->note = $request->note;
    $predefined_note->save();

    return response()->json([
        'message' => 'Predefined note created successfully',
    ], 200);
    
   }

}   