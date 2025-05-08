<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Clients;
use App\Models\Media;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\DB; // Import DB facade

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
        if ($request->has('industry')) {
            $matchStage['industry_type'] = ['$regex' => $request->industry, '$options' => 'i'];
        }

        // Filter by status
        if ($request->has('status')) {
            $matchStage['status'] = $request->status;
        }

        // Filter by client priority
        if ($request->has('priority')) {
            $matchStage['client_priority'] = $request->priority;
        }

        // Ensure $matchStage is not empty
        $matchCondition = !empty($matchStage) ? [['$match' => $matchStage]] : [];

        // Run aggregation
        $Clients = Clients::raw(function ($collection) use ($matchCondition) {
            return $collection->aggregate(array_merge($matchCondition, [
                // Lookup country
                ['$lookup' => [
                    'from' => 'countries',
                    'let' => ['countryId' => ['$toInt' => '$country']], // Convert country to integer
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$id', '$$countryId']]]]
                    ],
                    'as' => 'country_data'
                ]],
                ['$unwind' => ['path' => '$country_data', 'preserveNullAndEmptyArrays' => true]],

                // Lookup state
                ['$lookup' => [
                    'from' => 'states',
                    'let' => ['stateId' => ['$toInt' => '$state']], // Convert state to integer
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$id', '$$stateId']]]]
                    ],
                    'as' => 'state_data'
                ]],
                ['$unwind' => ['path' => '$state_data', 'preserveNullAndEmptyArrays' => true]],

                // Lookup city
                ['$lookup' => [
                    'from' => 'cities',
                    'let' => ['cityId' => ['$toInt' => '$city']], // Convert city to integer
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$id', '$$cityId']]]]
                    ],
                    'as' => 'city_data'
                ]],
                ['$unwind' => ['path' => '$city_data', 'preserveNullAndEmptyArrays' => true]],

                // Add fields for total projects and in-progress projects
                ['$addFields' => [
                    'total_projects' => ['$size' => ['$ifNull' => ['$projects', []]]], // Treat missing projects as an empty array
                    'in_progress_projects' => [
                        '$size' => [
                            '$filter' => [
                                'input' => ['$ifNull' => ['$projects', []]], // Treat missing projects as an empty array
                                'as' => 'project',
                                'cond' => [
                                    '$eq' => ['$$project.status_name', 'In Progress'] // Match status name
                                ]
                            ]
                        ]
                    ]
                ]],

                // Sort clients by created_at in descending order
                ['$sort' => ['created_at' => -1]], // Newest clients first

                // Project the required fields
                ['$project' => [
                    'company_name' => 1,
                    'first_name' => 1,
                    'last_name' => 1,
                    'about_client' => 1,
                    'client_type' => 1,
                    'profile_photo' => 1,
                    'country_data' => 1, // Include raw country_data for debugging
                    'state_data' => 1,   // Include raw state_data for debugging
                    'city_data' => 1,    // Include raw city_data for debugging
                    'country' => ['$ifNull' => ['$country_data.name', null]], // Use country name or null
                    'state' => ['$ifNull' => ['$state_data.name', null]],     // Use state name or null
                    'city' => ['$ifNull' => ['$city_data.name', null]],       // Use city name or null
                    'industry_type' => 1,
                    'status' => 1,
                    'client_priority' => 1,
                    'preferred_communication' => 1,
                    'client_notes' => 1,
                    'referral_source' => 1,
                    'account_manager_id' => 1,
                    'client_id' => 1,
                    '_id' => 1,
                    'total_projects' => 1,
                    'in_progress_projects' => 1
                ]]
            ]));
        });

        return response()->json($Clients, 200);
    }

    // Store a new Clients
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'about_client' => 'nullable|string',
            'client_type' => 'required|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            'industry_type' => 'nullable|string',
            'status' => 'required|string',
            'client_priority' => 'nullable|string',
            'preferred_communication' => 'nullable|string',
            'client_notes' => 'nullable|string',
            'referral_source' => 'nullable|string',
            'account_manager_id' => 'nullable|exists:users,_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        // Check if the country exists in the "countries" collection with the given id
        $countryExists = DB::getMongoDB()->selectCollection('countries')->findOne([
            'id' => (int) $request->country
        ]);


        if (!$countryExists) {
            return response()->json([
                'message' => 'The specified country does not exist.'
            ], 422);
        }
        // Check if the state exists in the "states" collection with the given id and country_id
        $stateExists = DB::getMongoDB()->selectCollection('states')->findOne([
            'id' => (int) $request->state,
            'country_id' => (int) $request->country
        ]);
        if (!$stateExists) {
            return response()->json([
                'message' => 'The specified state does not exist in the given country.'
            ], 422);
        }


        // Check if the city exists in the "city" collection with the given id, country_id and state_id
        $cityExists = DB::getMongoDB()->selectCollection('cities')->findOne([
            'id' => (int) $request->city,
            'country_id' => (int) $request->country,
            'state_id' => (int) $request->state
        ]);
        if (!$cityExists) {
            return response()->json([
                'message' => 'The specified city does not exist in the given country and state.'
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
        // $holiday = Clients::findOrFail($id);
        // return response()->json($holiday);

        // Fetch the client by ID
        $client = Clients::findOrFail($id);

        // Fetch the country name from the "countries" collection
        $country = DB::getMongoDB()->selectCollection('countries')->findOne(['id' => (int) $client->country]);
        $countryName = $country ? $country['name'] : null;

        // Fetch the state name from the "states" collection
        $state = DB::getMongoDB()->selectCollection('states')->findOne([
            'id' => (int) $client->state,
            'country_id' => (int) $client->country
        ]);
        $stateName = $state ? $state['name'] : null;

        // Fetch the city name from the "cities" collection
        $city = DB::getMongoDB()->selectCollection('cities')->findOne([
            'id' => (int) $client->city,
            'state_id' => (int) $client->state,
            'country_id' => (int) $client->country
        ]);
        $cityName = $city ? $city['name'] : null;

        // Add the names to the client data
        $clientData = $client->toArray();
        $clientData['country_name'] = $countryName;
        $clientData['state_name'] = $stateName;
        $clientData['city_name'] = $cityName;

        return response()->json($clientData);        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $client = Clients::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'about_client' => 'nullable|string',
            'client_type' => 'required|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            'industry_type' => 'nullable|string',
            'status' => 'required|string',
            'client_priority' => 'nullable|string',
            'preferred_communication' => 'nullable|string',
            'client_notes' => 'nullable|string',
            'referral_source' => 'nullable|string',
            'account_manager_id' => 'nullable|exists:users,_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate country if provided
        if ($request->has('country')) {
            $countryExists = DB::getMongoDB()->selectCollection('countries')->findOne([
                'id' => (int) $request->country
            ]);

            if (!$countryExists) {
                return response()->json([
                    'message' => 'The specified country does not exist.'
                ], 422);
            }
        }

        // Validate state if provided
        if ($request->has('state') && $request->has('country')) {
            $stateExists = DB::getMongoDB()->selectCollection('states')->findOne([
                'id' => (int) $request->state,
                'country_id' => (int) $request->country
            ]);

            if (!$stateExists) {
                return response()->json([
                    'message' => 'The specified state does not exist in the given country.'
                ], 422);
            }
        }

        // Validate city if provided
        if ($request->has('city') && $request->has('state') && $request->has('country')) {
            $cityExists = DB::getMongoDB()->selectCollection('cities')->findOne([
                'id' => (int) $request->city,
                'country_id' => (int) $request->country,
                'state_id' => (int) $request->state
            ]);

            if (!$cityExists) {
                return response()->json([
                    'message' => 'The specified city does not exist in the given country and state.'
                ], 422);
            }
        }

        $service = app(FileUploadService::class);

        if ($request->hasFile('profile_photo')) {
            // Delete old profile photo if exists
            if ($client->profile_photo) {
                $service->delete($client->profile_photo['file_path'], $client->profile_photo['media_id']);
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
