<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use App\Models\Holiday;
use App\Models\Media;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    // Get all holidays
    public function index()
    {
        return response()->json(Holiday::all());
    }

    // Store a new holiday
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'festival_name' => 'required|string|max:255',
            'festival_date' => 'required|date',
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'greeting_message' => 'nullable|string',
            'festival_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('festival_image')) {
            $imagePath = $this->fileUploadService->upload($request->file('festival_image'), 'uploads', $request->user->id);
        }

        $holiday = Holiday::create([
            'festival_name' => $request->festival_name,
            'festival_date' => $request->festival_date,
            'color' => $request->color,
            'greeting_message' => $request->greeting_message,
            'festival_image' => $imagePath,
            'posted_by' => $request->user->id
        ]);

        return response()->json(['message' => 'Holiday created successfully', 'data' => $holiday], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $holiday = Holiday::findOrFail($id);
        return response()->json($holiday);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $holiday = Holiday::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'festival_name' => 'required|string|max:255',
            'festival_date' => 'required|date',
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'greeting_message' => 'nullable|string',
            'festival_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('festival_image')) {
            // Delete old festival image from storage and media collection
            if (!empty($holiday->festival_image['media_id'])) {
                $media = Media::find($holiday->festival_image['media_id']);

                if ($media) {
                    $this->fileUploadService->delete($media->file_path); // Delete file from storage
                    $media->delete(); // Remove media record from database
                }
            }
            if ($holiday->festival_image) {
                $this->fileUploadService->delete($holiday->festival_image);
            }
            $imagePath = $this->fileUploadService->upload($request->file('festival_image'), 'uploads', $request->user->id);
            $holiday->festival_image = $imagePath;
        }

        $holiday->update($request->only(['festival_name', 'date', 'color', 'greeting_message']));

        return response()->json(['message' => 'Holiday updated successfully', 'data' => $holiday]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $holiday = Holiday::findOrFail($id);

        // Check if the holiday has an associated image
        if (!empty($holiday->festival_image['media_id'])) {
            $media = Media::find($holiday->festival_image['media_id']);

            if ($media) {
                $this->fileUploadService->delete($media->file_path); // Delete file from storage
                $media->delete(); // Remove media record from database
            }
        }

        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted successfully']);
    }
}
