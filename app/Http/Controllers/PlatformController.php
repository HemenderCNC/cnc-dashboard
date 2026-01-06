<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlatformController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Platform::orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:platforms,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = trim($request->name); // Trim spaces and convert to lowercase

        $platform = Platform::create(['name' => $name]);
        // $platform = Platform::create($request->only('name'));
        return response()->json($platform, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $platform = Platform::find($id);
        if (!$platform) {
            return response()->json(['message' => 'Platform not found'], 404);
        }
        return response()->json($platform, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $platform = Platform::find($id);
        if (!$platform) {
            return response()->json(['message' => 'Platform not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:platforms,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = trim($request->name); // Trim spaces and convert to lowercase

        $platform->update(['name' => $name]);
        // $platform->update($request->only('name'));
        return response()->json($platform, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $platform = Platform::find($id);
        if (!$platform) {
            return response()->json(['message' => 'Platform not found'], 404);
        }

        $platform->delete();
        return response()->json(['message' => 'Platform deleted successfully'], 200);
    }
}
