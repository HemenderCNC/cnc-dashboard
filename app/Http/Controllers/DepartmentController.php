<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Support\Facades\Validator;
class DepartmentController extends Controller
{
    public function addDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:departments,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $department = Department::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);
        return response()->json(['message' => 'Department created successfully', 'department' => $department], 201);
    }

    public function editDepartment(Request $request, $id)
    {
        // Validate the incoming data using Validator::make
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:departments,name,' . $id,
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the Department by ID
        $department = Department::find($id);

        // Check if Department exists
        if (!$department) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        // Update the Department
        $department->name = $request->name;
        $department->save();

        return response()->json(['message' => 'Department updated successfully', 'department' => $department], 200);
    }

    public function deleteDepartment($id)
    {
        // Validate if the Department exists
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:departments,id', // Check if the Department ID exists in the 'Departments' table
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the Department by ID
        $department = Department::find($id);

        // Check if Department exists
        if (!$department) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        // Delete the Department
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully'], 200);
    }


    /**
     * Get Department data by ID.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDepartmentById($id)
    {
        // Find the Department by ID
        $department = Department::find($id);

        // Check if the Department exists
        if (!$department) {
            return response()->json([
                'message' => 'Department not found',
            ], 404);
        }

        // Return the Department data
        return response()->json([
            'message' => 'Department retrieved successfully',
            'data' => $department,
        ], 200);
    }

    public function getAllDepartments()
    {
        // Fetch all Departments from the database
        $departments = Department::all();

        return response()->json(['departments' => $departments], 200);
    }
}
