<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Get activity logs with optional filters.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        // Filter by user ID
        if ($request->has('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        // Filter by IP address
        if ($request->has('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        // Filter by action type (created, updated, deleted)
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Pagination (default: 10 per page)
        $logs = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 10));

        return response()->json($logs);
    }

    public function getActivityLogs(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,_id',
            'first_name' => 'nullable|string',
            'last_name'  => 'nullable|string',
            'email'      => 'nullable|string|email',
            'action'     => 'nullable|string|in:created,updated,deleted',
            'page'       => 'nullable|integer|min:1',
            'per_page'      => 'nullable|integer|min:1|max:100'
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }


        // Convert dates to MongoDB-compatible format (ISODate)
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;
        $userId = $request->input('user_id');



        // Query activity logs
        $query = ActivityLog::query();

        // Apply date filter if provided
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // Apply user filter if provided
        if ($userId) {
            $query->where('user._id', $request->user_id);
        }

        // Filter by first name
        if ($request->has('first_name')) {
            $query->where('user.first_name', 'like', '%' . $request->first_name . '%');
        }

        // Filter by last name
        if ($request->has('last_name')) {
            $query->where('user.last_name', 'like', '%' . $request->last_name . '%');
        }

        // Filter by email
        if ($request->has('email')) {
            $query->where('user.email', $request->email);
        }

        // Filter by action type (created, updated, deleted)
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        $perPage = $request->input('per_page', 10); // Default to 10 per page
        $page = $request->input('page', 1); // Default to page 1
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);
        // Paginate results
        //$activityLogs = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Activity logs retrieved successfully',
            'data' => $logs
        ]);
    }
}
