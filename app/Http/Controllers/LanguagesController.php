<?php

namespace App\Http\Controllers;

use App\Models\Languages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LanguagesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Languages::all(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:languages,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $languages = Languages::create($request->only('name'));
        return response()->json($languages, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $languages = Languages::find($id);
        if (!$languages) {
            return response()->json(['message' => 'Languages not found'], 404);
        }
        return response()->json($languages, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $languages = Languages::find($id);
        if (!$languages) {
            return response()->json(['message' => 'Languages not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:languages,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $languages->update($request->only('name'));
        return response()->json($languages, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $languages = Languages::find($id);
        if (!$languages) {
            return response()->json(['message' => 'Languages not found'], 404);
        }

        $languages->delete();
        return response()->json(['message' => 'Languages deleted successfully'], 200);
    }
}
