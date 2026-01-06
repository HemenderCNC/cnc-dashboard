<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bugs;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;


class BugsController extends Controller
{
    public function index()
    {
        return response()->json(Bugs::with('users')->orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created bug.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'required|string',
            'link' => 'nullable|url',
            'module' => 'nullable|string',
            'type' => 'nullable|string',
            'priority' => 'nullable|string',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $media = null;
        if ($request->hasFile('media')) {
            $service = app(FileUploadService::class);
            $media = $service->upload($request->file('media'), 'uploads', $request->user->id);
        } 

        $bug = Bugs::create([
            'user_id'     => $request->user->id, // always use authenticated user
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'link'        => $request->input('link'),
            'status'      => $request->input('status'),
            'module'      => $request->input('module'),
            'type'      => $request->input('type'),
            'priority'      => $request->input('priority'),
            'media'       => $media,
        ]);

        return response()->json($bug, 201);
        
    }

    /**
     * Display the specified bug.
     */
    public function show($id)
    {
        $bug = Bugs::with('users')->find($id);
        if (!$bug) {
            return response()->json(['message' => 'Bug not found'], 404);
        }
        return response()->json($bug, 200);
    }

    /**
     * Update the specified bug.
     */
    public function update(Request $request, $id)
    {
        $bug = Bugs::find($id);
        if (!$bug) {
            return response()->json(['message' => 'Bug not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'required|string',
            'link' => 'nullable|url',
            'module' => 'nullable|string',
            'type' => 'nullable|string',
            'priority' => 'nullable|string',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $media = $bug->media; // Preserve current media if not updated
        if ($request->hasFile('media')) {
            $service = app(FileUploadService::class);
            $media = $service->upload($request->file('media'), 'uploads', $request->user->id);
        }

        $bug->update([
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'link'        => $request->input('link'),
            'module'      => $request->input('module'),
            'type'      => $request->input('type'),
            'priority'      => $request->input('priority'),
            'status'      => $request->input('status'),
            'media'       => $media,
        ]);

        return response()->json($bug, 200);
    }

    /**
     * Remove the specified bug.
     */
    public function destroy($id)
    {
        $bug = Bugs::find($id);
        if (!$bug) {
            return response()->json(['message' => 'Bug not found'], 404);
        }

        $bug->delete();
        return response()->json(['message' => 'Bug deleted successfully'], 200);
    }
}
