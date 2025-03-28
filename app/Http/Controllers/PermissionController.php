<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\PermissionModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\PersonalAccessToken;
use App\Helpers\MailHelper;
use Illuminate\Support\Str;


class PermissionController extends Controller
{
    /**
     * Get all permissions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllPermissions()
    {
        // Fetch all permissions from the database
        // $permissions = Permission::orderBy('created_at', 'desc')->get();
        // Fetch all permissions with module details
        $permissions = Permission::with('module')->orderBy('created_at', 'desc')->get();

        return response()->json(['permissions' => $permissions], 200);
    }


    // Edit permission
    public function editPermission(Request $request, $id)
    {
        // Find permission by ID
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }
        // Generate slug from name
        $slug = Str::slug(trim($request->name), '_');
        // Ensure slug is unique
        if (Permission::where('slug', $slug)->where('_id', '!=', $permission->_id)->exists()) {
            return response()->json(['error' => 'Slug must be unique'], 400);
        }


        // Validate the incoming data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // 'module_id' => 'required|string|max:255|exists:permissions_modules,_id',
            'module_id' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!PermissionModule::where('_id', $value)->exists()) {
                        $fail('The selected module id is invalid.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Update permission
        $permission->update([
            'name' => strtolower(trim($request->name)),
            'slug' => $slug,
            'module_id' => $request->module_id,
        ]);

    // Fetch the updated permission with related module details
    $permission = Permission::with('module')->find($permission->_id);

        return response()->json([
            'message' => 'Permission updated successfully!',
            'permission' => $permission
        ], 200);
    }

    // Delete permission
    public function deletePermission($id)
    {
        // Step 1: Find the permission by ID
        // Find permission by ID
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        // Step 2: Remove the permission ID from the `permissions` array of all roles
        Role::where('permissions', $id)->update([
            '$pull' => ['permissions' => $id]
        ]);

        // Step 3: Delete the permission
        // Delete permission
        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully'], 200);
    }


    // Add permission
    public function addPermission(Request $request)
    {

        // Generate slug from name
        $slug = Str::slug(trim($request->name), '_');
        // Ensure slug is unique
        if (Permission::where('slug', $slug)->exists()) {
            return response()->json(['error' => 'Slug must be unique'], 400);
        }


        // Validate the incoming data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            // 'module_id' => 'required|string|max:255|exists:permissions_modules,_id',
            'module_id' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!PermissionModule::where('_id', $value)->exists()) {
                        $fail('The selected module id is invalid.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create new permission
        $permission = Permission::create([
            'name' => strtolower(trim($request->name)),
            'slug' => $slug,
            'module_id' => $request->module_id,
        ]);
        return response()->json([
            'permission' => $permission
        ], 200);
    }

    // Get permission by ID
    public function getPermissionById($id)
    {
        // Find permission by ID
        // $permission = Permission::find($id);

        // Find permission by ID with module details
        $permission = Permission::with('module')->find($id);


        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        return response()->json([
            'permission' => $permission
        ], 200);
    }
}
