<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GeneralSettings;
use App\Models\LoginSession;
use App\Services\FileUploadService;
use App\Services\GeneralSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;



class GeneralSettingsController extends Controller
{

    public function index(Request $request)
    {
        $total_hrs = '00:00:00';
        $total_working_hrs = '00:00:00';
        $total_break_hrs = '00:00:00';
        if($request->user){
            $userId = $request->user->id;
            $currentDate = Carbon::now()->toDateString();
            $LoginSession = LoginSession::where('employee_id', $userId)
            ->where('date', $currentDate)
            ->first();
            if ($LoginSession) {
                $total_hrs = $LoginSession->total_login_time ?? '00:00:00';
                $total_working_hrs = $LoginSession->total_working_time ?? '00:00:00';
                $total_break_hrs = $LoginSession->total_break_time ?? '00:00:00';
            }
        }
        $settings = GeneralSettings::first();
        $settingsArray = $settings ? $settings->toArray() : [];

        return response()->json(array_merge($settingsArray, [
            'total_hrs' => $total_hrs,
            'total_working_hrs' => $total_working_hrs,
            'total_break_hrs' => $total_break_hrs,
        ]));
    }

    public function update(Request $request)
    {
        // Validation Rules
        $validator = Validator::make($request->all(), [
            'site_title' => 'nullable|string|max:255',
            'total_leaves_per_person' => 'nullable|integer|min:1',
            'logo_standard' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'logo_small' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:1024',
        ]);

        // If validation fails, return error response
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Fetch existing settings or create a new one
        $settings = GeneralSettings::firstOrNew([]);

        // Handle Logo Uploads with Old File Deletion
        $logo = $settings->logo ?? [];

        $service = app(FileUploadService::class);
        if ($request->hasFile('logo_standard')) {
            // Delete old file before saving a new one
            if (!empty($logo['standard'])) {
                $service->delete($logo['standard']['file_path'],$logo['standard']['media_id']);
            }
            $logo['standard'] = $service->upload($request->file('logo_standard'), 'uploads', $request->user->id);
        }

        if ($request->hasFile('logo_small')) {
            // Delete old file before saving a new one
            if (!empty($logo['small'])) {
                $service->delete($logo['small']['file_path'],$logo['small']['media_id']);
            }
            $logo['small'] = $service->upload($request->file('logo_small'), 'uploads', $request->user->id);
        }

        // Update Settings Fields
        $settings->logo = $logo;
        $settings->site_title = $request->input('site_title', $settings->site_title);
        $settings->leave_settings = [
            'total_leaves_per_person' => $request->input('total_leaves_per_person', $settings->leave_settings['total_leaves_per_person'] ?? 12)
        ];
        $settings->created_by = $request->user->id;

        $settings->save();

        // Clear Cache
        Cache::forget('general_settings');

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => $settings
        ]);
    }
}
