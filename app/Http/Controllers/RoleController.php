<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;
class RoleController extends Controller
{
    public function addRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,_id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $role = Role::create([
            'name' => $request->name,
            'permissions' => $request->permissions,
        ]);
        return response()->json(['message' => 'Role created successfully', 'role' => $role], 201);
    }

    public function editRole(Request $request, $id)
    {
        // Validate the incoming data using Validator::make
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name,' . $id, // Ignore the current role ID for uniqueness
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,_id',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the role by ID
        $role = Role::find($id);
        
        // Check if role exists
        if (!$role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        // Update the role
        $role->name = $request->name;
        $role->permissions = $request->permissions;
        $role->save();

        return response()->json(['message' => 'Role updated successfully', 'role' => $role], 200);
    }

    public function deleteRole($id)
    {
        // Validate if the role exists
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:roles,id', // Check if the role ID exists in the 'roles' table
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the role by ID
        $role = Role::find($id);
        
        // Check if role exists
        if (!$role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        // Delete the role
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully'], 200);
    }


    /**
     * Get role data by ID.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoleById($id)
    {
        // Find the role by ID
        $role = Role::find($id);

        // Check if the role exists
        if (!$role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        // Return the role data
        return response()->json([
            'message' => 'Role retrieved successfully',
            'data' => $role,
        ], 200);
    }

    public function getAllRoles()
    {
        // Fetch all roles from the database
        $roles = Role::all();

        return response()->json(['roles' => $roles], 200);
    }
}
