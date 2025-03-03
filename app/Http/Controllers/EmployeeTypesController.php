<?php

namespace App\Http\Controllers;

use App\Models\EmployeeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class EmployeeTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(EmployeeType::orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:employee_types,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $employee_type = EmployeeType::create($request->only('name'));
        return response()->json($employee_type, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $employee_type = EmployeeType::find($id);
        if (!$employee_type) {
            return response()->json(['message' => 'Employee Type not found'], 404);
        }
        return response()->json($employee_type, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $employee_type = EmployeeType::find($id);
        if (!$employee_type) {
            return response()->json(['message' => 'Employee Type not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:employee_types,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee_type->update($request->only('name'));
        return response()->json($employee_type, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $employee_type = EmployeeType::find($id);
        if (!$employee_type) {
            return response()->json(['message' => 'Employee Type not found'], 404);
        }

        $employee_type->delete();
        return response()->json(['message' => 'Employee Type deleted successfully'], 200);
    }
}
