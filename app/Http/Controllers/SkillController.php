<?php

namespace App\Http\Controllers;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class SkillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Skill::orderBy('created_at', 'desc')->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:skills,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $skill = Skill::create($request->only('name'));
        return response()->json($skill, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $skill = Skill::find($id);
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }
        return response()->json($skill, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $skill = Skill::find($id);
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:skills,name,' . $id
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $skill->update($request->only('name'));
        return response()->json($skill, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $skill = Skill::find($id);
        if (!$skill) {
            return response()->json(['message' => 'Skill not found'], 404);
        }

        $skill->delete();

        // Remove this skill ID from all users' skills array
        User::where('skills', $id)->update([
            '$pull' => ['skills' => $id] // Remove skill ID from array
        ]);

        return response()->json(['message' => 'Skill deleted successfully'], 200);
    }
}
