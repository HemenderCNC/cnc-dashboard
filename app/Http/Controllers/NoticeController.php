<?php

namespace App\Http\Controllers;
use App\Models\Notice;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    // Get all notices
    public function index(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);
        if ($limit == -1) {
            $leaves = Notice::orderBy('created_at', 'desc')->get();

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
        $leaves = Notice::orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $leaves->items(),
            'meta' => [
                'page' => $leaves->currentPage(),
                'limit' => $leaves->perPage(),
                'total' => $leaves->total(),
                'total_pages' => ceil($leaves->total() / $leaves->perPage()),
            ]
        ], 200);
    }

    // Create a new notice
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:visible,hidden',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $notice = Notice::create([
            'message' => $request->message,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?? 'visible',
        ]);

        return response()->json(['message' => 'Notice created successfully', 'notice' => $notice], 201);
    }

    // Get a single notice by ID
    public function show($id)
    {
        $notice = Notice::find($id);

        if (!$notice) {
            return response()->json(['message' => 'Notice not found'], 404);
        }

        return response()->json(['message' => 'Notice details', 'notice' => $notice], 200);
    }

    // Update a notice
    public function update(Request $request, $id)
    {
        $notice = Notice::find($id);

        if (!$notice) {
            return response()->json(['message' => 'Notice not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:visible,hidden',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $notice->update($request->only(['message', 'start_date', 'end_date', 'status']));

        return response()->json(['message' => 'Notice updated successfully', 'notice' => $notice], 200);
    }

    //Update Notice Status
    public function changeStatus(Request $request, $id)
    {
        $notice = Notice::find($id);

        if (!$notice) {
            return response()->json(['message' => 'Notice not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:visible,hidden',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $notice->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Notice status updated successfully',
            'notice' => $notice
        ], 200);
    }


    // Delete a notice
    public function destroy($id)
    {
        $notice = Notice::find($id);

        if (!$notice) {
            return response()->json(['message' => 'Notice not found'], 404);
        }

        $notice->delete();
        return response()->json(['message' => 'Notice deleted successfully'], 200);
    }

    // Get active notice
    public function getVisibleNotices()
    {
        $today = Carbon::today()->toDateString();
        $notices = Notice::where('status', 'visible')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();

        if ($notices->isEmpty()) {
            return response()->json([
                'message' => 'No visible notices found',
                'notices' => []
            ], 200);
        }

        return response()->json([
            'message' => 'Visible notices retrieved successfully',
            'notices' => $notices
        ], 200);
    }
}
