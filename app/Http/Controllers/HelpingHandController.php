<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use App\Models\HelpingHand;
use Illuminate\Http\Request;
use App\Notifications\PushNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class HelpingHandController extends Controller
{
    public function index(Request $request){
        $matchStage = (object)[]; // Ensure it's an object, not an empty array
        if ($request->user->role->name === 'Employee') {
            $userId = $request->user->id;
            $matchStage->{'$or'} = [
                (object) ['from_id' => $userId],
                (object) ['to_id' => $userId]
            ];
        }
        // Filter by client ID
        if ($request->has('project_id')) {
            $matchStage->project_id = $request->project_id;
        }
        if ($request->has('status')) {
            $matchStage->status = $request->status;
        }
        // Ensure matchStage is not empty
        if (empty((array) $matchStage)) {
            $matchStage = (object)[]; // Empty object for MongoDB
        }

        // MongoDB Aggregation Pipeline
        $HelpingHand = HelpingHand::raw(function ($collection) use ($matchStage) {
            return $collection->aggregate([
                ['$match' => $matchStage],
                ['$addFields' => [
                    'project_id' => ['$toObjectId' => '$project_id'],
                    'from_id' => ['$toObjectId' => '$from_id'],
                    'to_id' => ['$toObjectId' => '$to_id']
                ]],
                ['$lookup' => [
                    'from' => 'projects',   // Collection name for Users (Project Manager)
                    'localField' => 'project_id',
                    'foreignField' => '_id',
                    'as' => 'project'
                ]],
                ['$lookup' => [
                    'from' => 'users',   // Collection name for Users (Project Manager)
                    'localField' => 'from_id',
                    'foreignField' => '_id',
                    'as' => 'from'
                ]],
                ['$lookup' => [
                    'from' => 'users',   // Collection name for Users (Created By)
                    'localField' => 'to_id',
                    'foreignField' => '_id',
                    'as' => 'to'
                ]],
                ['$sort' => ['created_at' => -1]],
                ['$project' => [
                    'project_id' => 1,
                    'project' => 1,
                    'from_id' => 1,
                    'from' => 1,
                    'to_id' => 1,
                    'to' => 1,
                    'issue' => 1,
                    'status' => 1,
                    'updated_at' => 1,
                    'created_at' => 1
                ]]
            ]);
        });

        return response()->json($HelpingHand, 200);
    }

    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,_id',
            'from_id' => 'required|exists:users,_id',
            'to_id' => 'required|exists:users,_id',
            'issue' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $HelpingHand = HelpingHand::create([
            'project_id' => $request->project_id,
            'from_id' => $request->from_id,
            'to_id' => $request->to_id,
            'issue' => $request->issue,
            'status' => 'pending',
        ]);
        return response()->json($HelpingHand, 201);
    }

    public function updateStatus(Request $request,$id){
        $HelpingHand = HelpingHand::find($id);
        if (!$HelpingHand) {
            return response()->json(['message' => 'Helping Hand not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $status = $request->status;
        if($status == 'accepted'){
            $timelog = array(
                'start_time' => now()->format('H:i'),
                'end_time' => now()->addMinute()->format('H:i'),
            );
            $HelpingHand->time_log = $timelog;
        }
        if($status == 'completed'){
            $timelog = $HelpingHand->time_log;
            $timelog['end_time'] = now()->format('H:i');
            $HelpingHand->time_log = $timelog;
        }
        if($status == 'rescheduled'){
            $reschedule_time = $request->reschedule_time;
            $HelpingHand->schedule_time = $reschedule_time;
        }
        $HelpingHand->update([
                'status'=>$request->status
            ]);
        return response()->json($HelpingHand, 200);
    }

    public function sendNotification(Request $request){
        Log::info('FCM token');
        $title = 'CP GET OUT';
        $body = 'This fully integrates FCM push notifications from Laravel to React. 🚀';
        $user = User::find('678e3c50dc822828470e8c42');
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $user->notify(new PushNotification($title, $body));

        return response()->json(['message' => 'Notification sent successfully']);
    }
    public function setToken(Request $request){
        $fcm_token = $request->fcm_token;
        $user = User::find('678e3c50dc822828470e8c42');
        $user->fcm_token = $fcm_token;
        $user->save();
    }
}
