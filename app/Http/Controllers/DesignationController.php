<?php

namespace App\Http\Controllers;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class DesignationController extends Controller
{
    /**
     * Display a listing of designations.
     */
    public function index()
    {
        return response()->json(Designation::all(), 200);
    }

    /**
     * Store a newly created designation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:designations,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $designation = Designation::create($request->only('name'));
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
            'name' => 'required|string|unique:designations,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $designation->update($request->only('name'));
        return response()->json($designation, 200);
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
