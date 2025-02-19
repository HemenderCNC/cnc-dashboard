<?php

namespace App\Http\Controllers;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Skill::all(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:skills,name'
        ]);

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

        $request->validate([
            'name' => 'required|string|unique:skills,name,' . $id
        ]);

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
