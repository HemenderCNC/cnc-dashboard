<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Option;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

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
            'qualification_level_id' => 'nullable|exists:options,_id',
            'certification_name' => 'nullable|string',
            'year_of_completion' => 'nullable|date',
            'qualification_document' => 'nullable|file|mimes:pdf,jpeg,png|max:2048',

            // Work Information
            'company_email' => 'nullable|email|unique:users,email',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,_id',
            'department_id' => 'nullable|exists:options,_id',
            'designation_id' => 'nullable|exists:options,_id',
            'joining_date' => 'nullable|date',
            'in_out_time' => 'nullable|string',
            'adharcard_number' => 'nullable|string',
            'pancard_number' => 'nullable|string',
            'employment_type_id' => 'nullable|exists:options,_id',
            'employee_status_id' => 'nullable|exists:options,_id',
            'created_by' => 'required|exists:users,_id',
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
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'joining_date' => $request->joining_date,
            'in_out_time' => $request->in_out_time,
            'adharcard_number' => $request->adharcard_number,
            'pancard_number' => $request->pancard_number,
            'employment_type_id' => $request->employment_type_id,
            'employee_status_id' => $request->employee_status_id,

            // 'email' => $request->email,
            
            // 'role' => $request->role, 
            'created_by' => $request->user->id,

            // Add other fields here...
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
            // 'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',

            // Address Information
            'residential_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'emergency_contact_number' => 'nullable|string',

            // Qualification
            'qualification_level_id' => 'nullable|exists:options,_id',
            'certification_name' => 'nullable|string',
            'year_of_completion' => 'nullable|date',
            // 'qualification_document' => 'nullable|file|mimes:pdf,jpeg,png|max:2048',

            // Work Information
            'company_email' => 'nullable|email|unique:users,email,'. $id,
            'username' => 'required|string|unique:users,username,'. $id,
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,_id',
            'department_id' => 'nullable|exists:options,_id',
            'designation_id' => 'nullable|exists:options,_id',
            'joining_date' => 'nullable|date',
            'in_out_time' => 'nullable|string',
            'employment_type_id' => 'nullable|exists:options,_id',
            'employee_status_id' => 'nullable|exists:options,_id',
            'created_by' => 'required|exists:users,_id',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
    

        //Handel Upload
        // $service = app(FileUploadService::class);
        // $profilePhoto = $service->upload($request->file('profile_photo'), 'uploads', $request->user->id);
        // $qualificationDocument = $service->upload($request->file('qualification_document'), 'uploads', $request->user->id);

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
            // 'profile_photo' => $profilePhoto,

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
            // 'qualification_document' => $qualificationDocument,


            // Work Information
            'email' => $request->company_email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'joining_date' => $request->joining_date,
            'in_out_time' => $request->in_out_time,
            'employment_type_id' => $request->employment_type_id,
            'employee_status_id' => $request->employee_status_id,

            'created_by' => $request->user->id,
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
        $user = User::find($id);

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
    public function getAllUsers()
    {
        // Fetch all users
        $users = User::all();

        // Check if there are any users
        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No users found',
            ], 404);
        }

        // Return the list of users
        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ], 200);
    }


    //Update user profile picture
    public function updateProfilePicture(Request $request, $id)
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
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Validate image type and size
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle profile picture upload
        $service = app(FileUploadService::class);
        $newProfilePhoto = $service->upload($request->file('profile_photo'), 'uploads', $request->user->id);

        if ($user->profile_photo) {
            // Delete the old profile picture
            $service->delete($user->profile_photo['file_path']);
        }

        // Update the user's profile photo
        $user->update([
            'profile_photo' => $newProfilePhoto,
        ]);

        // Return a success message
        return response()->json([
            'message' => 'Profile picture updated successfully!',
            'user' => $user,
            'profile_picture_url' => $newProfilePhoto,
        ], 200);
    }

    //Update user profile picture
    public function deleteProfilePicture(Request $request, $id)
    {
        // Find user by ID
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Handle profile picture upload
        $service = app(FileUploadService::class);

        if ($user->profile_photo) {
            // Delete the profile photo
            $deleted = $service->delete($user->profile_photo['file_path']);

            if ($deleted) {
                // Remove profile photo data from the user record
                $user->update(['profile_photo' => null]);
                return response()->json(['message' => 'Profile photo deleted successfully!'], 200);
            } else {
                return response()->json(['message' => 'Failed to delete profile photo'], 500);
            }
        }

        // Update the user's profile photo
        // $user->update([
        //     'profile_photo' => '',
        // ]);

        // Return a success message
        return response()->json(['message' => 'No profile photo to delete'], 404);
    }

    //Update user Qualification document
    public function updateQualificationDocument(Request $request, $id)
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
            'qualification_document' => 'required|file|mimes:pdf,jpeg,png|max:2048', // Validate image type and size
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle profile picture upload
        $service = app(FileUploadService::class);
        $newProfilePhoto = $service->upload($request->file('qualification_document'), 'uploads', $request->user->id);

        if ($user->profile_photo) {
            // Delete the old profile picture
            $service->delete($user->profile_photo['file_path']);
        }

        // Update the user's profile photo
        $user->update([
            'qualification_document' => $newProfilePhoto,
        ]);

        // Return a success message
        return response()->json([
            'message' => 'Qualification document updated successfully!',
            'user' => $user,
            'profile_picture_url' => $newProfilePhoto,
        ], 200);
    }

    //Update user Qualification document
    public function deleteQualificationDocument(Request $request, $id)
    {
        // Find user by ID
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Handle profile picture upload
        $service = app(FileUploadService::class);

        if ($user->qualification_document) {
            // Delete the profile photo
            $deleted = $service->delete($user->qualification_document['file_path']);

            if ($deleted) {
                // Remove profile photo data from the user record
                $user->update(['qualification_document' => null]);
                return response()->json(['message' => 'Qualification document deleted successfully!'], 200);
            } else {
                return response()->json(['message' => 'Failed to delete Qualification document'], 500);
            }
        }

        // Update the user's profile photo
        // $user->update([
        //     'profile_photo' => '',
        // ]);

        // Return a success message
        return response()->json(['message' => 'No profile photo to delete'], 404);
    }


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
            $service->delete($user->profile_photo['file_path']);
        }

        // Delete qualification document if exists
        if (!empty($user->qualification_document['file_path'])) {
            $service->delete($user->qualification_document['file_path']);
        }

        // Delete the user
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully!',
        ], 200);
    }


    


}
