<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Support\Facades\Validator;
class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json(Department::orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created designation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:departments,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = strtolower(trim($request->name)); // Trim spaces and convert to lowercase

        $department = Department::create(['name' => $name]);
        // $department = Department::create($request->only('name'));
        return response()->json($department, 201);
    }

    /**
     * Display the specified designation.
     */
    public function show($id)
    {
        $department = Department::find($id);
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }
        return response()->json($department, 200);
    }

    /**
     * Update the specified designation.
     */
    public function update(Request $request, $id)
    {
        $department = Department::find($id);
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:departments,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = strtolower(trim($request->name)); // Trim spaces and convert to lowercase

        $department->update(['name' => $name]);
        // $department->update($request->only('name'));
        return response()->json($department, 200);
    }

    /**
     * Remove the specified Department.
     */
    public function destroy($id)
    {
        $department = Department::find($id);
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $department->delete();
        return response()->json(['message' => 'Department deleted successfully'], 200);
    }
}
