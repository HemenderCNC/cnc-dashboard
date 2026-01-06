<?php

namespace App\Http\Controllers;

use App\Models\EmployeeStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(EmployeeStatus::orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:employee_statuses,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $name = trim($request->name); // Trim spaces and convert to lowercase
        $worklocation = EmployeeStatus::create(['name' => $name]);
        // $worklocation = EmployeeStatus::create($request->only('name'));
        return response()->json($worklocation, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $worklocation = EmployeeStatus::find($id);
        if (!$worklocation) {
            return response()->json(['message' => 'Employee Status not found'], 404);
        }
        return response()->json($worklocation, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $worklocation = EmployeeStatus::find($id);
        if (!$worklocation) {
            return response()->json(['message' => 'Employee Status not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:employee_statuses,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $name = trim($request->name); // Trim spaces and convert to lowercase
        $worklocation->update(['name' => $name]);
        // $worklocation->update($request->only('name'));
        return response()->json($worklocation, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $worklocation = EmployeeStatus::find($id);
        if (!$worklocation) {
            return response()->json(['message' => 'Employee Status not found'], 404);
        }

        $worklocation->delete();
        return response()->json(['message' => 'Employee Status deleted successfully'], 200);
    }
}
