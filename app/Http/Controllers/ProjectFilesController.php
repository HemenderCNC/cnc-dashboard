<?php

namespace App\Http\Controllers;

use App\Models\ProjectFiles;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class ProjectFilesController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|string',
            'document_name' => 'required|string',
            'document' =>  'required|file|max:102400',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $project_id = $request->project_id;
        $project = Project::where('id',$project_id)->first();
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        $document = null;
        if ($request->hasFile('document')) {
            $document = $this->fileUploadService->upload($request->file('document'), 'uploads', $request->user->id);
        }
        $platform = ProjectFiles::create([
            'document_name' => $request->document_name,
            'document' => $document,
            'project_id' => $request->project_id,
            'created_by' => $request->user->id,
        ]);
        return response()->json($platform, 201);
    }

    public function update(Request $request,$id)
    {
        $ProjectFiles = ProjectFiles::find($id);
        if (!$ProjectFiles) {
            return response()->json(['message' => 'Project File not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|string',
            'document_name' => 'required|string',
            'document' =>  'nullable|file|max:102400',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $project_id = $request->project_id;
        $project = Project::where('id',$project_id)->first();
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        if ($request->hasFile('document')) {
            $service = app(FileUploadService::class);
            if ($ProjectFiles->document) {
                $service->delete($ProjectFiles->document['file_path'],$ProjectFiles->document['media_id']);
            }

            $profilePhoto = $service->upload($request->file('document'), 'uploads', $request->user->id);
            $ProjectFiles->document = $profilePhoto;
            // $ProjectFiles->save();
        }
        $ProjectFiles->update([
            'document_name' => $request->document_name,
            'project_id' => $request->project_id,
        ]);
        return response()->json($ProjectFiles, 201);
    }

    public function show(Request $request,$id){
        $project = Project::where('id',$id)->first();
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        $ProjectFiles = ProjectFiles::where('project_id',$id)->with('creator')->get();
        return response()->json($ProjectFiles, 201);
    }
    public function detail(Request $request,$id){
        $ProjectFiles = ProjectFiles::where('id',$id)->with('creator')->first();
        if (!$ProjectFiles) {
            return response()->json(['message' => 'Project Files not found'], 404);
        }

        return response()->json($ProjectFiles, 201);
    }
    public function destroy(string $id)
    {
        $ProjectFiles = ProjectFiles::find($id);
        if (!$ProjectFiles) {
            return response()->json(['message' => 'Project Files not found'], 404);
        }
        $service = app(FileUploadService::class);
        if ($ProjectFiles->document) {
            $service->delete($ProjectFiles->document['file_path'],$ProjectFiles->document['media_id']);
        }
        $ProjectFiles->delete();
        return response()->json(['message' => 'Project Files deleted successfully'], 200);
    }
}
