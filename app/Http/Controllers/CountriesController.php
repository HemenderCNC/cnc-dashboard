<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Countries;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; // Import DB facade

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

        /**
     * States
     */
    /**
     * Display the specified Country's states.
     */
    public function getStatesOf(string $id)
    {
        // Convert the country ID to an integer
        $countryID = (int) $id;

        // Fetch the country using a raw MongoDB query
        $country = DB::getMongoDB()->selectCollection('countries')->findOne(['id' => $countryID]);

        // Check if the country exists
        if (!$country) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        // Fetch states using a raw MongoDB query
        $states = DB::getMongoDB()->selectCollection('states')->find(['country_id' => $countryID])->toArray();

        // Check if states exist
        if (empty($states)) {
            return response()->json(['message' => 'No states found for this country'], 404);
        }

        // Return the country and its states
        return response()->json([
            'country' => $country,
            'states' => $states,
        ], 200);
    }
    /**
     * Display the specified State's cities.
     */
    public function getCitiesOf(string $country_id, string $state_id)
    {
        // Convert the IDs to integers
        $countryID = (int) $country_id;
        $stateID = (int) $state_id;

        // Fetch the country using a raw MongoDB query
        $country = DB::getMongoDB()->selectCollection('countries')->findOne(['id' => $countryID]);

        // Check if the country exists
        if (!$country) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        // Fetch the state using a raw MongoDB query
        $state = DB::getMongoDB()->selectCollection('states')->findOne(['id' => $stateID, 'country_id' => $countryID]);

        // Check if the state exists
        if (!$state) {
            return response()->json(['message' => 'State not found for this country'], 404);
        }

        // Fetch cities using a raw MongoDB query
        $cities = DB::getMongoDB()->selectCollection('cities')->find(['state_id' => $stateID])->toArray();

        // Check if cities exist
        if (empty($cities)) {
            return response()->json(['message' => 'No cities found for this state'], 404);
        }

        // Return the country, state, and cities
        return response()->json([
            'country' => $country,
            'state' => $state,
            'cities' => $cities,
        ], 200);
    }
}
