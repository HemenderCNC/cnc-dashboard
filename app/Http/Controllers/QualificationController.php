<?php

namespace App\Http\Controllers;
use App\Models\Qualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class QualificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Qualification::all(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:qualifications,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $qualification = Qualification::create($request->only('name'));
        return response()->json($qualification, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $qualification = Qualification::find($id);
        if (!$qualification) {
            return response()->json(['message' => 'Qualification not found'], 404);
        }
        return response()->json($qualification, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $qualification = Qualification::find($id);
        if (!$qualification) {
            return response()->json(['message' => 'Qualification not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:qualifications,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $qualification->update($request->only('name'));
        return response()->json($qualification, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $qualification = Qualification::find($id);
        if (!$qualification) {
            return response()->json(['message' => 'Qualification not found'], 404);
        }

        $qualification->delete();
        return response()->json(['message' => 'Qualification deleted successfully'], 200);
    }
}
