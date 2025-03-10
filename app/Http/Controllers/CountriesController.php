<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Countries;
use Illuminate\Support\Facades\Validator;

class CountriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Countries::orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:countries,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $Countries = Countries::create([
            'name' => $request->name,
        ]);

        return response()->json($Countries, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $Countries = Countries::find($id);
        if (!$Countries) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        return response()->json($Countries);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $Countries = Countries::find($id);
        if (!$Countries) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        $request->validate([
            'name' => 'required|unique:countries,name,' . $id,
        ]);

        $Countries->update(['name' => $request->name]);

        return response()->json($Countries);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $Countries = Countries::find($id);
        if (!$Countries) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        $Countries->delete();
        return response()->json(['message' => 'Country deleted successfully']);
    }
}
