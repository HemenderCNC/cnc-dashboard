<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Clients;
use App\Models\Media;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class ClientsController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    // Get all Clients
    public function index(Request $request)
    {
        $matchStage = []; // Initialize as an array

        // Search by name or employee_id
        if ($request->has('search') && !empty($request->search)) {
            $search = trim($request->search);
            $matchStage['$or'] = [
                ['first_name' => ['$regex' => $search, '$options' => 'i']],  // Case-insensitive name search
                ['last_name' => ['$regex' => $search, '$options' => 'i']]
            ];
        }

        // Filter by industry type
        if ($request->has('industry_type')) {
            $matchStage['industry_type'] = ['$regex' => $request->industry_type, '$options' => 'i'];
        }

        // Filter by status
        if ($request->has('status')) {
            $matchStage['status'] = $request->status;
        }

        // Filter by client priority
        if ($request->has('client_priority')) {
            $matchStage['client_priority'] = $request->client_priority;
        }

        // Ensure $matchStage is not empty
        $matchCondition = !empty($matchStage) ? [['$match' => $matchStage]] : [];

        // Run aggregation
        $Clients = Clients::raw(function ($collection) use ($matchCondition) {
            return $collection->aggregate(array_merge($matchCondition, [
                ['$project' => [ // Use $project instead of $client
                    'company_name' => 1,
                    'first_name' => 1,
                    'last_name' => 1,
                    'about_client' => 1,
                    'client_type' => 1,
                    'profile_photo' => 1,
                    'country' => 1,
                    'state' => 1,
                    'city' => 1,
                    'industry_type' => 1,
                    'status' => 1,
                    'client_priority' => 1,
                    'preferred_communication' => 1,
                    'client_notes' => 1,
                    'referral_source' => 1,
                    'account_manager_id' => 1,
                    '_id' => 1, // Correct field name (MongoDB uses `_id`)
                ]]
            ]));
        });

        return response()->json($Clients, 200);
    }

    // Store a new Clients
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name'=> 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'about_client' => 'nullable|string',
            'client_type'=> 'required|string',
            'profile_photo'=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'country'=> 'nullable|string',
            'state'=> 'nullable|string',
            'city'=> 'nullable|string',
            'industry_type'=> 'nullable|string',
            'status'=> 'required|string',
            'client_priority'=> 'nullable|string',
            'preferred_communication'=> 'nullable|string',
            'client_notes'=> 'nullable|string',
            'referral_source'=> 'nullable|string',
            'account_manager_id'=> 'nullable|exists:users,_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $profile_photo = null;
        if ($request->hasFile('profile_photo')) {
            $profile_photo = $this->fileUploadService->upload($request->file('profile_photo'), 'uploads', $request->user->id);
        }

        $holiday = Clients::create([
            'company_name' => $request->company_name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'about_client' => $request->about_client,
            'client_type' => $request->client_type,
            'profile_photo' => $profile_photo,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'industry_type' => $request->industry_type,
            'status' => $request->status,
            'client_priority' => $request->client_priority,
            'preferred_communication' => $request->preferred_communication,
            'client_notes' => $request->client_notes,
            'referral_source' => $request->referral_source,
            'account_manager_id' => $request->account_manager_id,
            'created_by' => $request->user->id,
        ]);

        return response()->json(['message' => 'Clients created successfully', 'data' => $holiday], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $holiday = Clients::findOrFail($id);
        return response()->json($holiday);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $client = Clients::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'company_name'=> 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'about_client' => 'nullable|string',
            'client_type'=> 'required|string',
            'profile_photo'=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'country'=> 'nullable|string',
            'state'=> 'nullable|string',
            'city'=> 'nullable|string',
            'industry_type'=> 'nullable|string',
            'status'=> 'required|string',
            'client_priority'=> 'nullable|string',
            'preferred_communication'=> 'nullable|string',
            'client_notes'=> 'nullable|string',
            'referral_source'=> 'nullable|string',
            'account_manager_id'=> 'nullable|exists:users,_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $service = app(FileUploadService::class);

        if ($request->hasFile('profile_photo')) {

            // Delete old profile photo if exists
            if ($client->profile_photo) {
                $service->delete($client->profile_photo['file_path'],$client->profile_photo['media_id']);
            }

            $profilePhoto = $service->upload($request->file('profile_photo'), 'uploads', $request->user->id);
            $client->profile_photo = $profilePhoto;
        }


        $client->update([
                'company_name' => $request->company_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'about_client' => $request->about_client,
                'client_type' => $request->client_type,
                'country' => $request->country,
                'state' => $request->state,
                'city' => $request->city,
                'industry_type' => $request->industry_type,
                'status' => $request->status,
                'client_priority' => $request->client_priority,
                'preferred_communication' => $request->preferred_communication,
                'client_notes' => $request->client_notes,
                'referral_source' => $request->referral_source,
                'account_manager_id' => $request->account_manager_id,
                'created_by' => $request->user->id,
        ]);

        return response()->json(['message' => 'Client updated successfully', 'data' => $client]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $holiday = Clients::findOrFail($id);

        // Check if the holiday has an associated image
        if (!empty($holiday->festival_image['media_id'])) {
            $media = Media::find($holiday->festival_image['media_id']);

            if ($media) {
                $this->fileUploadService->delete($media->file_path,$holiday->festival_image['media_id']); // Delete file from storage and record from Media Table
            }
        }

        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted successfully']);
    }
}
