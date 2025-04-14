<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use MongoDB\BSON\ObjectId;

class UserController extends Controller
{
    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Basic Information
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'gender' => 'required|string',
            'contact_number' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'personal_email' => 'nullable|email',
            'blood_group' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',

            // Address Information
            'residential_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'emergency_contact_number' => 'nullable|string',

            // Qualification
            'qualification_level_id' => 'nullable|exists:qualifications,_id',
            'certification_name' => 'nullable|string',
            'year_of_completion' => 'nullable|date',
            'qualification_document' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:2048',

            // Work Information
            'company_email' => 'nullable|email|unique:users,email',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,_id',
            'department_id' => 'nullable|exists:departments,_id',
            'designation_id' => 'nullable|exists:designations,_id',
            'joining_date' => 'nullable|date',
            'in_time' => 'nullable|string',
            'out_time' => 'nullable|string',
            'adharcard_number' => 'nullable|string|size:12',
            'pancard_number' => 'nullable|string',
            'employment_type_id' => 'nullable|exists:employee_types,_id',
            'employee_status_id' => 'nullable|exists:employee_statuses,_id',
            'work_location_id' => 'nullable|exists:work_locations,_id',
            'created_by' => 'required|exists:users,_id',

            //Skills
            'skills' => 'nullable|array',
            'skills.*' => 'exists:skills,_id',

            //Bank details
            'account_holde_name' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'account_number' => 'nullable|string',
            'account_type' => 'nullable|string',
            'bank_ifsc_code' => 'nullable|string',
            'bank_branch_location' => 'nullable|string',

            //document details
            'document_type_id' => 'nullable|exists:document_types,_id',
            'document' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:2048',

            'reporting_manager_id' => 'nullable|exists:users,_id',
        ]);
        if ($validator->fails()) {

                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422); // 422 Unprocessable Entity status code
        }

        // Auto-generate User ID
        $lastUser = User::latest('_id')->first();
        $userId = $lastUser ? 'CNC' . str_pad(((int)substr($lastUser->_id, -3)) + 1, 3, '0', STR_PAD_LEFT) : 'CNC001';

        // Handle file uploads
        // $profilePhoto = $this->uploadFile($request->file('profile_photo'), $request->user->id);
        // $qualificationDocument = $this->uploadFile($request->file('qualification_document'), $request->user->id);
        $service = app(FileUploadService::class);
        $profilePhoto = $service->upload($request->file('profile_photo'), 'uploads', $request->user->id);
        $qualificationDocument = $service->upload($request->file('qualification_document'), 'uploads', $request->user->id);
        $document = '';
        if ($request->hasFile('document')) {
            $document = $service->upload($request->file('document'), 'uploads', $request->user->id);
        }

        // Save User
        $user = User::create([
            'official_id' => $userId,
            // Basic Information
            'name' => $request->first_name,
            'last_name' => $request->last_name,
            'gender' => $request->gender,
            'contact_number' => $request->contact_number,
            'birthdate' => $request->birthdate,
            'personal_email' => $request->personal_email,
            'blood_group' => $request->blood_group,
            'marital_status' => $request->marital_status,
            'nationality' => $request->nationality,
            'profile_photo' => $profilePhoto,

            // Address Information
            'residential_address' => $request->residential_address,
            'permanent_address' => $request->permanent_address,
            'country' => $request->country,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'emergency_contact_number' => $request->emergency_contact_number,

            // Qualification
            'qualification_level_id' => $request->qualification_level_id,
            'certification_name' => $request->certification_name,
            'year_of_completion' => $request->year_of_completion,
            'qualification_document' => $qualificationDocument,


            // Work Information
            'email' => $request->company_email,
            'work_location_id' => $request->work_location_id,
            'office_location' => $request->office_location,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'joining_date' => $request->joining_date,
            'in_time' => $request->in_time,
            'out_time' => $request->out_time,
            'adharcard_number' => $request->adharcard_number,
            'pancard_number' => $request->pancard_number,
            'employment_type_id' => $request->employment_type_id,
            'employee_status_id' => $request->employee_status_id,
            'work_location_id' => $request->work_location_id,
            // 'email' => $request->email,

            // 'role' => $request->role,
            'created_by' => $request->user->id,
            //Skills
            'skills' => $request->skills,

            //Bank details
            'account_holde_name' => $request->account_holde_name,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_type' => $request->account_type,
            'bank_ifsc_code' => $request->bank_ifsc_code,
            'bank_branch_location' => $request->bank_branch_location,

            //document details
            'document_type_id' => $request->document_type_id,
            'document' => $document,

            'reporting_manager_id' => $request->reporting_manager_id,
        ]);
        return response()->json(['message' => 'User added successfully!', 'user' => $user], 201);
    }

    //Edit user
    public function editUser(Request $request, $id)
    {
        // Find user by ID
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Validate the incoming data
        $validator = Validator::make($request->all(), [
            // Basic Information
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'gender' => 'required|string',
            'contact_number' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'personal_email' => 'nullable|email',
            'blood_group' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',

            // Address Information
            'residential_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'emergency_contact_number' => 'nullable|string',

            // Qualification
            'qualification_level_id' => 'nullable|exists:qualifications,_id',
            'certification_name' => 'nullable|string',
            'year_of_completion' => 'nullable|date',
            'qualification_document' => 'nullable|file|mimes:pdf,jpeg,png|max:2048',

            // Work Information
            'company_email' => 'nullable|email|unique:users,email,'. $id,
            'username' => 'required|string|unique:users,username,'. $id,
            // 'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,_id',
            'department_id' => 'nullable|exists:departments,_id',
            'designation_id' => 'nullable|exists:designations,_id',
            'joining_date' => 'nullable|date',
            'in_time' => 'nullable|string',
            'out_time' => 'nullable|string',
            'adharcard_number' => 'nullable|string|size:12',
            'pancard_number' => 'nullable|string',
            'employment_type_id' => 'nullable|exists:employee_types,_id',
            'employee_status_id' => 'nullable|exists:employee_statuses,_id',
            'work_location_id' => 'nullable|exists:work_locations,_id',
            // 'created_by' => 'required|exists:users,_id',


            //Skills
            'skills' => 'nullable|array',
            'skills.*' => 'exists:skills,_id',

            //Bank details
            'account_holde_name' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'account_number' => 'nullable|string',
            'account_type' => 'nullable|string',
            'bank_ifsc_code' => 'nullable|string',
            'bank_branch_location' => 'nullable|string',

            //document details
            'document_type_id' => 'nullable|exists:document_types,_id',
            'document' => 'nullable|file|mimes:pdf,jpeg,png|max:2048',

            'reporting_manager_id' => 'nullable|exists:users,_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }


        //Handel Upload
        //CREATED seperate API to edit docs
        // $service = app(FileUploadService::class);
        // $profilePhoto = $service->upload($request->file('profile_photo'), 'uploads', $request->user->id);
        // $qualificationDocument = $service->upload($request->file('qualification_document'), 'uploads', $request->user->id);


        // Handle File Uploads Only If Present in Request
        $service = app(FileUploadService::class);

        if ($request->hasFile('profile_photo')) {

            // Delete old profile photo if exists
            if ($user->profile_photo) {
                $service->delete($user->profile_photo['file_path'],$user->profile_photo['media_id']);
            }

            $profilePhoto = $service->upload($request->file('profile_photo'), 'uploads', $request->user->id);
            $user->profile_photo = $profilePhoto;
        }

        if ($request->hasFile('qualification_document')) {

            // Delete old qualification document if exists
            if ($user->qualification_document) {
                $service->delete($user->qualification_document['file_path'],$user->qualification_document['media_id']);
            }

            $qualificationDocument = $service->upload($request->file('qualification_document'), 'uploads', $request->user->id);
            $user->qualification_document = $qualificationDocument;
        }

        if ($request->hasFile('document')) {

            // Delete old qualification document if exists
            if ($user->document) {
                $service->delete($user->document['file_path'],$user->document['media_id']);
            }

            $document = $service->upload($request->file('document'), 'uploads', $request->user->id);
            $user->document = $document;
        }



        // Update user data
        $user->update([
            // Basic Information
            'name' => $request->first_name,
            'last_name' => $request->last_name,
            'gender' => $request->gender,
            'contact_number' => $request->contact_number,
            'birthdate' => $request->birthdate,
            'personal_email' => $request->personal_email,
            'blood_group' => $request->blood_group,
            'marital_status' => $request->marital_status,
            'nationality' => $request->nationality,
            // 'profile_photo' => $profilePhoto, //CREATED seperate API to edit docs

            // Address Information
            'residential_address' => $request->residential_address,
            'permanent_address' => $request->permanent_address,
            'country' => $request->country,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'emergency_contact_number' => $request->emergency_contact_number,

            // Qualification
            'qualification_level_id' => $request->qualification_level_id,
            'certification_name' => $request->certification_name,
            'year_of_completion' => $request->year_of_completion,
            // 'qualification_document' => $qualificationDocument, //CREATED seperate API to edit docs


            // Work Information
            'email' => $request->company_email,
            'username' => $request->username,
            // 'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'joining_date' => $request->joining_date,
            'in_time' => $request->in_time,
            'out_time' => $request->out_time,
            'adharcard_number' => $request->adharcard_number,
            'pancard_number' => $request->pancard_number,
            'employment_type_id' => $request->employment_type_id,
            'employee_status_id' => $request->employee_status_id,
            'work_location_id' => $request->work_location_id,
            'office_location' => $request->office_location,

            'created_by' => $request->user->id,

            // Skills
            'skills' => $request->skills, // Save array of skill IDs

            //Bank details
            'account_holde_name' => $request->account_holde_name,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_type' => $request->account_type,
            'bank_ifsc_code' => $request->bank_ifsc_code,
            'bank_branch_location' => $request->bank_branch_location,

            //document details
            'document_type_id' => $request->document_type_id,

            'reporting_manager_id' => $request->reporting_manager_id,
        ]);

        // Return a success message
        return response()->json([
            'message' => 'User updated successfully!',
            'user' => $user
        ], 200);
    }

    /**
     * Get a user by their ID.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserById($id)
    {
        // Find the user by ID
        $user = User::with(['employeeType','department','employeeStatus','designation','workLocation','documentType','qualification','reportingManager'])->find($id);

        // Check if the user exists
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Return the user data
        return response()->json([
            'message' => 'User retrieved successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Get all users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUsers(Request $request)
{
    $filters = [];

    // Check if the current user's role is "Employee"
    if ($request->user->role->name === 'Employee') {
        $filters['_id'] = new ObjectId($request->user->id);
    }
        // 🔍 Search Filter (by name or employee_id)
        if ($request->has('search') && !empty($request->search)) {
            $search = trim($request->search);
            $filters['$or'] = [
                ['name' => ['$regex' => $search, '$options' => 'i']],  // Case-insensitive name search
                ['employee_id' => ['$regex' => $search, '$options' => 'i']]
            ];
        }

        // 🏢 Department Filter
        if ($request->has('department_id') && !empty($request->department_id)) {
            $filters['department_id'] = $request->department_id;
        }

        // 🎭 Role Filter
        if ($request->has('role_id') && !empty($request->role_id)) {
            $filters['role_id'] = $request->role_id;
        }

        // 🏬 Office Location Filter
        if ($request->has('work_location_id') && !empty($request->work_location_id)) {
            $filters['work_location_id'] = $request->work_location_id;
        }

        // 🚀 Employee Status Filter
        if ($request->has('employee_status_id') && !empty($request->employee_status_id)) {
            $filters['employee_status_id'] = $request->employee_status_id;
        }

        // 📅 Joining Date Range Filter
        if ($request->has('joining_date_from') && $request->has('joining_date_to')) {
            $filters['joining_date'] = [
                '$gte' => $request->joining_date_from,
                '$lte' => $request->joining_date_to,
            ];
        }

    // 🔄 Fetch users with filters applied
    $users = User::raw(function ($collection) use ($filters) {
        return $collection->aggregate([
            ['$match' => count($filters) > 0 ? $filters : (object)[]], // Ensure valid match object

            // Lookup Department
            [
                '$lookup' => [
                    'from' => 'departments',
                    'let' => ['deptId' => ['$toObjectId' => '$department_id']],
                    'pipeline' => [
                        ['$match' => [
                            '$expr' => [
                                '$eq' => ['$_id', '$$deptId']
                            ]
                        ]]
                    ],
                    'as' => 'department'
                ]
            ],
            ['$unwind' => ['path' => '$department', 'preserveNullAndEmptyArrays' => true]],

            [
                '$lookup' => [
                    'from' => 'employee_types',
                    'let' => ['deptId' => ['$toObjectId' => '$employment_type_id']],
                    'pipeline' => [
                        ['$match' => [
                            '$expr' => [
                                '$eq' => ['$_id', '$$deptId']
                            ]
                        ]]
                    ],
                    'as' => 'employee_type'
                ]
            ],
            ['$unwind' => ['path' => '$employee_type', 'preserveNullAndEmptyArrays' => true]],

            [
                '$lookup' => [
                    'from' => 'designations',
                    'let' => ['deptId' => ['$toObjectId' => '$designation_id']],
                    'pipeline' => [
                        ['$match' => [
                            '$expr' => [
                                '$eq' => ['$_id', '$$deptId']
                            ]
                        ]]
                    ],
                    'as' => 'designation'
                ]
            ],
            ['$unwind' => ['path' => '$designation', 'preserveNullAndEmptyArrays' => true]],

            // Lookup Role
            ['$lookup' => [
                'from' => 'roles',
                'localField' => 'role_id',
                'foreignField' => '_id',
                'as' => 'role'
            ]],
            ['$unwind' => ['path' => '$role', 'preserveNullAndEmptyArrays' => true]],

            // Lookup Office
            ['$lookup' => [
                'from' => 'work_locations',
                'localField' => 'work_location_id',
                'foreignField' => '_id',
                'as' => 'work_location'
            ]],
            ['$unwind' => ['path' => '$work_location', 'preserveNullAndEmptyArrays' => true]],

            // Lookup Employee Status (Fix ObjectId issue)
            ['$lookup' => [
                'from' => 'employee_statuses',
                'let' => ['statusId' => ['$toObjectId' => '$employee_status_id']],
                'pipeline' => [
                    ['$match' => ['$expr' => ['$eq' => ['$_id', '$$statusId']]]]
                ],
                'as' => 'employee_status'
            ]],
            ['$unwind' => ['path' => '$employee_status', 'preserveNullAndEmptyArrays' => true]],
            ['$sort' => ['created_at' => -1]],

            // Project Only Required Fields
            ['$project' => [
                'name' => 1,
                '_id' => 1,
                'last_name' => 1,
                'gender' => 1,
                'contact_number' => 1,
                'birthdate' => 1,
                'personal_email' => 1,
                'blood_group' => 1,
                'marital_status' => 1,
                'office_location' => 1,
                'residential_address' => 1,
                'role_id' => 1,
                'username' => 1,
                'nationality' => 1,
                'employee_status_id' => 1,
                'employee_type' => 1,
                'designation' => 1,
                'profile_photo' => 1,
                'employee_id' => 1,
                'skills' => 1,
                'email' => 1,
                'reporting_manager_id' => 1,
                'joining_date' => 1,
                'department' => 1,
                'role' => 1,
                'work_location' => 1,
                'employee_status' => 1,
            ]]
        ]);
    });

    // Check if users exist
    if ($users->isEmpty()) {
        return response()->json([
            'message' => 'No users found',
        ], 404);
    }

    return response()->json([
        'message' => 'Users retrieved successfully',
        'data' => $users,
    ], 200);
}



    //Update user profile picture
    // public function updateProfilePicture(Request $request, $id)
    // {
    //     // Find user by ID
    //     $user = User::find($id);

    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'User not found',
    //         ], 404);
    //     }

    //     // Validate the incoming data
    //     $validator = Validator::make($request->all(), [
    //         'profile_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Validate image type and size
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     // Handle profile picture upload
    //     $service = app(FileUploadService::class);
    //     $newProfilePhoto = $service->upload($request->file('profile_photo'), 'uploads', $request->user->id);

    //     if ($user->profile_photo) {
    //         // Delete the old profile picture
    //         $service->delete($user->profile_photo['file_path']);
    //     }

    //     // Update the user's profile photo
    //     $user->update([
    //         'profile_photo' => $newProfilePhoto,
    //     ]);

    //     // Return a success message
    //     return response()->json([
    //         'message' => 'Profile picture updated successfully!',
    //         'user' => $user,
    //         'profile_picture_url' => $newProfilePhoto,
    //     ], 200);
    // }

    //Update user profile picture
    // public function deleteProfilePicture(Request $request, $id)
    // {
    //     // Find user by ID
    //     $user = User::find($id);

    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'User not found',
    //         ], 404);
    //     }

    //     // Handle profile picture upload
    //     $service = app(FileUploadService::class);

    //     if ($user->profile_photo) {
    //         // Delete the profile photo
    //         $deleted = $service->delete($user->profile_photo['file_path']);

    //         if ($deleted) {
    //             // Remove profile photo data from the user record
    //             $user->update(['profile_photo' => null]);
    //             return response()->json(['message' => 'Profile photo deleted successfully!'], 200);
    //         } else {
    //             return response()->json(['message' => 'Failed to delete profile photo'], 500);
    //         }
    //     }

    //     // Update the user's profile photo
    //     // $user->update([
    //     //     'profile_photo' => '',
    //     // ]);

    //     // Return a success message
    //     return response()->json(['message' => 'No profile photo to delete'], 404);
    // }

    //Update user Qualification document
    // public function updateQualificationDocument(Request $request, $id)
    // {
    //     // Find user by ID
    //     $user = User::find($id);

    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'User not found',
    //         ], 404);
    //     }

    //     // Validate the incoming data
    //     $validator = Validator::make($request->all(), [
    //         'qualification_document' => 'required|file|mimes:pdf,jpeg,png|max:2048', // Validate image type and size
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     // Handle profile picture upload
    //     $service = app(FileUploadService::class);
    //     $newProfilePhoto = $service->upload($request->file('qualification_document'), 'uploads', $request->user->id);

    //     if ($user->profile_photo) {
    //         // Delete the old profile picture
    //         $service->delete($user->profile_photo['file_path']);
    //     }

    //     // Update the user's profile photo
    //     $user->update([
    //         'qualification_document' => $newProfilePhoto,
    //     ]);

    //     // Return a success message
    //     return response()->json([
    //         'message' => 'Qualification document updated successfully!',
    //         'user' => $user,
    //         'profile_picture_url' => $newProfilePhoto,
    //     ], 200);
    // }

    //Update user Qualification document
    // public function deleteQualificationDocument(Request $request, $id)
    // {
    //     // Find user by ID
    //     $user = User::find($id);

    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'User not found',
    //         ], 404);
    //     }

    //     // Handle profile picture upload
    //     $service = app(FileUploadService::class);

    //     if ($user->qualification_document) {
    //         // Delete the profile photo
    //         $deleted = $service->delete($user->qualification_document['file_path']);

    //         if ($deleted) {
    //             // Remove profile photo data from the user record
    //             $user->update(['qualification_document' => null]);
    //             return response()->json(['message' => 'Qualification document deleted successfully!'], 200);
    //         } else {
    //             return response()->json(['message' => 'Failed to delete Qualification document'], 500);
    //         }
    //     }

    //     // Update the user's profile photo
    //     // $user->update([
    //     //     'profile_photo' => '',
    //     // ]);

    //     // Return a success message
    //     return response()->json(['message' => 'No profile photo to delete'], 404);
    // }


    /**
     * Delete a user by ID.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser($id)
    {
        // Find the user by ID
        $user = User::find($id);

        // Check if the user exists
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Initialize the file upload service
        $service = app(FileUploadService::class);

        // Delete profile photo if exists
        if (!empty($user->profile_photo['file_path'])) {
            $service->delete($user->profile_photo['file_path'],$user->profile_photo['media_id']);
        }

        // Delete qualification document if exists
        if (!empty($user->qualification_document['file_path'])) {
            $service->delete($user->qualification_document['file_path'],$user->qualification_document['media_id']);
        }

        // Delete the user
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully!',
        ], 200);
    }





}
