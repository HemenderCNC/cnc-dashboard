<?php

namespace App\Http\Controllers;
use App\Models\Designation;
use Illuminate\Http\Request;

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
        $request->validate([
            'name' => 'required|string|unique:designations,name'
        ]);

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

        $request->validate([
            'name' => 'required|string|unique:designations,name,' . $id
        ]);

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
