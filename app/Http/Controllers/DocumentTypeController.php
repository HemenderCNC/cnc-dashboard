<?php

namespace App\Http\Controllers;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class DocumentTypeController extends Controller
{
    /**
     * Display a listing of DocumentType.
     */
    public function index()
    {
        return response()->json(DocumentType::all(), 200);
    }

    /**
     * Store a newly created DocumentType.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:document_types,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $documenttype = DocumentType::create([
            'name' => $request->name,
        ]);
        return response()->json($documenttype, 201);
    }

    /**
     * Display the specified DocumentType.
     */
    public function show($id)
    {
        $documenttype = DocumentType::find($id);
        if (!$documenttype) {
            return response()->json(['message' => 'DocumentType not found'], 404);
        }
        return response()->json($documenttype, 200);
    }

    /**
     * Update the specified DocumentType.
     */
    public function update(Request $request, $id)
    {
        $documenttype = DocumentType::find($id);
        if (!$documenttype) {
            return response()->json(['message' => 'DocumentType not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:document_types,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $documenttype->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'DocumentType updated successfully',
            'documenttype' => $documenttype
        ], 200);
    }


    /**
     * Remove the specified DocumentType.
     */
    public function destroy($id)
    {
        $documenttype = DocumentType::find($id);
        if (!$documenttype) {
            return response()->json(['message' => 'DocumentType not found'], 404);
        }

        $documenttype->delete();
        return response()->json(['message' => 'DocumentType deleted successfully'], 200);
    }
}
