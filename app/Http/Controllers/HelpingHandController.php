<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use App\Models\HelpingHand;
use App\Models\Timesheet;
use App\Models\LoginSession;
use Illuminate\Http\Request;
use App\Notifications\PushNotification;
use App\Models\User;
use App\Models\Project;
use Carbon\Carbon;

class HelpingHandController extends Controller
{
    public function index(Request $request)
    {
        $matchStage = (object)[]; // Ensure it's an object, not an empty array
        $userId = $request->user->id;
        if ($request->user->role->slug === 'employee') {
            $matchStage->{'$or'} = [
                (object) ['from_id' => $userId],
                (object) ['to_id' => $userId]
            ];
        }
        $matchStage->{'$or'} = [
            (object) ['from_id' => $userId],
            (object) ['to_id' => $userId]
        ];
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

                // Convert IDs to ObjectId
                ['$addFields' => [
                    'project_id' => ['$toObjectId' => '$project_id'],
                    'task_id' => ['$toObjectId' => '$task_id'],
                    'from_id' => ['$toObjectId' => '$from_id'],
                    'to_id' => ['$toObjectId' => '$to_id']
                ]],

                // Lookup for Project
                ['$lookup' => [
                    'from' => 'projects',
                    'localField' => 'project_id',
                    'foreignField' => '_id',
                    'as' => 'project'
                ]],
                // Lookup for Task
                ['$lookup' => [
                    'from' => 'tasks',
                    'localField' => 'task_id',
                    'foreignField' => '_id',
                    'as' => 'task'
                ]],
                // Lookup for 'from' user
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'from_id',
                    'foreignField' => '_id',
                    'as' => 'from'
                ]],
                ['$unwind' => ['path' => '$from', 'preserveNullAndEmptyArrays' => true]],

                // Lookup for 'to' user
                ['$lookup' => [
                    'from' => 'users',
                    'localField' => 'to_id',
                    'foreignField' => '_id',
                    'as' => 'to'
                ]],

                // Unwind 'to' to access 'designation_id'
                ['$unwind' => ['path' => '$to', 'preserveNullAndEmptyArrays' => true]],

                // Convert 'to.designation_id' to ObjectId
                ['$addFields' => [
                    'to.designation_id' => ['$toObjectId' => '$to.designation_id'],
                    'from.designation_id' => ['$toObjectId' => '$from.designation_id']
                ]],

                // Lookup for Designation
                ['$lookup' => [
                    'from' => 'designations',
                    'localField' => 'to.designation_id',
                    'foreignField' => '_id',
                    'as' => 'to_designation'
                ]],
                ['$lookup' => [
                    'from' => 'designations',
                    'localField' => 'from.designation_id',
                    'foreignField' => '_id',
                    'as' => 'from_designation'
                ]],

                // Unwind 'to_designation' to get name
                ['$unwind' => ['path' => '$to_designation', 'preserveNullAndEmptyArrays' => true]],
                ['$unwind' => ['path' => '$from_designation', 'preserveNullAndEmptyArrays' => true]],

                // Add Designation Name to 'to'
                ['$addFields' => [
                    'to.designation_name' => '$to_designation.name',
                    'from.designation_name' => '$from_designation.name',
                ]],

                // Sorting by created_at descending
                ['$sort' => ['created_at' => -1]],

                // Projection
                ['$project' => [
                    'project_id' => 1,
                    'project' => 1,
                    'task_id' => 1,
                    'task' => 1,
                    'from_id' => 1,
                    'from' => 1,
                    'to_id' => 1,
                    'to' => 1,
                    'issue' => 1,
                    'status' => 1,
                    'schedule_time' => 1,
                    'time_log' => 1,
                    'updated_at' => 1,
                    'created_at' => 1
                ]]
            ]);
        });

        return response()->json($HelpingHand, 200);
    }

    public function create(Request $request)
    {
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
            'task_id' => $request->task_id,
            'from_id' => $request->from_id,
            'to_id' => $request->to_id,
            'issue' => $request->issue,
            'status' => 'pending',
        ]);
        $this->sendPushNotification($HelpingHand);
        return response()->json($HelpingHand, 201);
    }

    public function updateStatus(Request $request, $id)
    {
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


        if ($HelpingHand->status == 'canceled') {
            return response()->json(['message' => 'The Requester has canceled the request.'], 409);
        }
        $currentDate = Carbon::now()->toDateString();
        $status = $request->status;
        if ($status == 'accepted') {
            $user_id = $request->user->id;
            $HelpingHandCount = HelpingHand::where('status', 'accepted')->where('to_id', $user_id)->count();
            if ($HelpingHandCount > 0) {
                return response()->json(['message' => 'You are already working on a task. Please complete or cancel it before starting another.'], 409);
            }
            $timelog = array(
                'start_time' => now()->format('H:i'),
                'end_time' => now()->format('H:i'),
            );
            $HelpingHand->time_log = $timelog;

            Timesheet::where('employee_id', $user_id)->update(['status' => 'paused']);
            $currentDate = Carbon::now()->toDateString();
            $timesheet = Timesheet::create([
                'project_id' => $HelpingHand->project_id,
                'task_id' => $HelpingHand->task_id,
                'task_type' => 'Helping Hand',
                'employee_id' => $user_id,
                'dates' => [
                    [
                        'date' => $currentDate,
                        'time_log' => [
                            [
                                'start_time' => now()->format('H:i'),
                                'end_time' => now()->format('H:i'),
                            ]
                        ],
                    ]
                ], // Store as a plain PHP array
                'work_description' => 'Helping Hand',
                'status' => 'running',
            ]);
            // Handle break session in LoginSession
            $session = LoginSession::where('employee_id', $user_id)
                ->where('date', $currentDate)
                ->first();

            if ($session && $session->break === true) {
                $session->break = false;
                $session->save();
            }
        }
        if ($status == 'completed') {
            $user_id = $request->user->id;
            $timelog = $HelpingHand->time_log;
            $task_id = $HelpingHand->task_id;
            $timelog['end_time'] = now()->format('H:i');
            $HelpingHand->time_log = $timelog;
            $timesheet = Timesheet::where('status', 'running')->where('employee_id', $user_id)->where('task_id', $task_id)->first();
            if($timesheet) {
                $timesheet->status = 'paused';
                $timesheet->save();
                $session = LoginSession::where('employee_id', $user_id)
                ->where('date', $currentDate)
                ->first();

                if ($session && $session->break === false) {
                    $session->break = true;
                    $session->save();
                }
            }
        }
        if ($status == 'rescheduled') {
            $reschedule_time = $request->reschedule_time;
            $HelpingHand->schedule_time = $reschedule_time;
        }
        $HelpingHand->update([
            'status' => $request->status
        ]);
        $this->sendPushNotification($HelpingHand);
        return response()->json($HelpingHand, 200);
    }

    public static function sendPushNotification($HelpingHand)
    {
        if (empty($HelpingHand)) {
            return;
        }
        $status = $HelpingHand->status;
        $from_id = $HelpingHand->from_id;
        $to_id = $HelpingHand->to_id;

        if ($status == 'pending') {
            $from = User::find($from_id);
            $from_name = $from->name . ' ' . $from->last_name;
            $project = Project::find($HelpingHand->project_id);
            $project_name = $project->project_name;
            $title = '🤝 New Help Request!';
            $body = '✋ Employee ' . $from_name . ' has requested your help on 📌' . $project_name . '.               🚀Tap to respond!';
            $user = User::find($to_id);
            $user->notify(new PushNotification($title, $body));
        }
        if ($status == 'accepted') {
            $to = User::find($to_id);
            $to_name = $to->name . ' ' . $to->last_name;
            $project = Project::find($HelpingHand->project_id);
            $project_name = $project->project_name;
            $title = '✅ Help Request Accepted!';
            $body = '🎉 ' . $to_name . ' has accepted your help request for 📌 ' . $project_name . '. 🏆 Time to collaborate and get things done! 🚀';
            $user = User::find($from_id);
            $user->notify(new PushNotification($title, $body));
        }
        if ($status == 'declined') {
            $to = User::find($to_id);
            $to_name = $to->name . ' ' . $to->last_name;
            $project = Project::find($HelpingHand->project_id);
            $project_name = $project->project_name;
            $title = '❌ Help Request Declined';
            $body = '⚠️ ' . $to_name . ' has declined your help request for 📌 ' . $project_name . '. Don\'t worry! You can ask someone else for assistance. 💡';
            $user = User::find($from_id);
            $user->notify(new PushNotification($title, $body));
        }
        if ($status == 'canceled') {
            $from = User::find($from_id);
            $from_name = $from->name . ' ' . $from->last_name;
            $project = Project::find($HelpingHand->project_id);
            $project_name = $project->project_name;
            $title = '❌ Help Request Canceled';
            $body = '🔔 ' . $from_name . ' has canceled the help request for 📌 ' . $project_name . '. You’re no longer assigned to assist on this task.';
            $user = User::find($to_id);
            $user->notify(new PushNotification($title, $body));
        }
        if ($status == 'completed') {
            $from = User::find($from_id);
            $to = User::find($to_id);
            $project = Project::find($HelpingHand->project_id);
            $project_name = $project->project_name;

            $title = '✅ Help Request Completed';
            $body = '🎉 The help request for 📌 ' . $project_name . ' has been successfully completed. Great teamwork! 🚀';

            // Notify both users
            if ($from) {
                $from->notify(new PushNotification($title, $body));
            }
            if ($to) {
                $to->notify(new PushNotification($title, $body));
            }
        }
        if ($status == 'rescheduled') {
            $to = User::find($to_id);
            $from = User::find($from_id);
            $project = Project::find($HelpingHand->project_id);
            $project_name = $project->project_name;
            $to_name = $to->name . ' ' . $to->last_name;
            $new_time = $HelpingHand->schedule_time; // Assuming you store the rescheduled time in the model

            $title = '⏳ Help Request Rescheduled';
            $body = '🔄 Employee ' . $to_name . ' has rescheduled your help request for 📌 ' . $project_name . ' to 🕒 ' . $new_time . '. 📅 Tap to check the updated schedule!';

            // Notify requester
            if ($from) {
                $from->notify(new PushNotification($title, $body));
            }
        }

        return response()->json(['message' => 'Notification sent successfully']);
    }
    public function setToken(Request $request)
    {
        $fcm_token = $request->fcm_token;
        $userId = $request->user->id;
        $user = User::find($userId);
        $user->fcm_token = $fcm_token;
        $user->save();
    }
}
