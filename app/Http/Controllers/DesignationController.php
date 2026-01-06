<?php

namespace App\Http\Controllers;
use App\Models\Designation;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class DesignationController extends Controller
{
    /**
     * Display a listing of designations.
     */
    public function index()
    {
        return response()->json(Designation::orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created designation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:designations,name',
            'department_id' => 'required|exists:departments,_id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Fetch the department name using department_id
        $department = Department::find($request->department_id);

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }


        $designation = Designation::create([
            'name' => trim($request->name),
            'department_id' => $request->department_id
        ]);
        return response()->json($designation, 201);
    }

    /**
     * Display the specified designation.
     */
    public function show($id)
    {
        $designation = Designation::find($id);
        if (!$designation) {
            return response()->json(['message' => 'Designation not found'], 404);
        }
        return response()->json($designation, 200);
    }

    /**
     * Update the specified designation.
     */
    public function update(Request $request, $id)
    {
        $designation = Designation::find($id);
        if (!$designation) {
            return response()->json(['message' => 'Designation not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:designations,name,' . $id,
            'department_id' => 'required|exists:departments,_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $designation->update([
            'name' => trim($request->name),
            'department_id' => $request->department_id
        ]);

        return response()->json([
            'message' => 'Designation updated successfully',
            'designation' => $designation
        ], 200);
    }


    /**
     * Remove the specified designation.
     */
    public function destroy($id)
    {
        $designation = Designation::find($id);
        if (!$designation) {
            return response()->json(['message' => 'Designation not found'], 404);
        }

        $designation->delete();
        return response()->json(['message' => 'Designation deleted successfully'], 200);
    }
}
