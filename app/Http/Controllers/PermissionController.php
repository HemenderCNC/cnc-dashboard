<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\PersonalAccessToken;
use App\Helpers\MailHelper;

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
        $permissions = Permission::orderBy('created_at', 'desc')->get();

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

        // Validate the incoming data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Update permission
        $permission->update([
            'name' => strtolower(trim($request->name)),
        ]);

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
        // Validate the incoming data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Step 1: Create the new permission
        // Create new permission
        $permission = Permission::create([
            'name' => strtolower(trim($request->name)),
        ]);

        // Step 2: Update the permissions array in the "roles" collection
        $role = Role::find('678e3b7ab9a4b5377a0d1799');

        if ($role) {
            // Add the newly created permission to the role's permissions array
            $role->permissions = array_merge($role->permissions, [$permission->_id]);
            $role->save();

            return response()->json([
                'message' => 'Permission added and role updated successfully!',
                'permission' => $permission,
                'role' => $role
            ], 201);
        } else {
            // If role is not found
            return response()->json([
                'message' => 'Role not found'
            ], 404);
        }
    }

    // Get permission by ID
    public function getPermissionById($id)
    {
        // Find permission by ID
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        return response()->json([
            'permission' => $permission
        ], 200);
    }
}
