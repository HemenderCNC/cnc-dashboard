<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IndustryTypes;
use Illuminate\Support\Facades\Validator;

class IndustryTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(IndustryTypes::orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:industry_types,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $IndustryTypes = IndustryTypes::create([
            'name' => strtolower(trim($request->name)),
        ]);

        return response()->json($IndustryTypes, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $IndustryTypes = IndustryTypes::find($id);
        if (!$IndustryTypes) {
            return response()->json(['message' => 'Industry Type not found'], 404);
        }

        return response()->json($IndustryTypes);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $IndustryTypes = IndustryTypes::find($id);
        if (!$IndustryTypes) {
            return response()->json(['message' => 'Industry Type not found'], 404);
        }

        $request->validate([
            'name' => 'required|unique:industry_types,name,' . $id,
        ]);

        $IndustryTypes->update(['name' => strtolower(trim($request->name))]);

        return response()->json($IndustryTypes);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $IndustryTypes = IndustryTypes::find($id);
        if (!$IndustryTypes) {
            return response()->json(['message' => 'Industry Type not found'], 404);
        }

        $IndustryTypes->delete();
        return response()->json(['message' => 'Industry Type deleted successfully']);
    }
}
