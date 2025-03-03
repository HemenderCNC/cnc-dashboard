<?php

namespace App\Services;

use App\Models\GeneralSettings;
use Illuminate\Support\Facades\Cache;

class GeneralSettingsService
{
    protected $cacheKey = 'general_settings';

    // ✅ Get a setting value
    public function getSetting($key, $default = null)
    {
        $settings = Cache::rememberForever($this->cacheKey, function () {
            return GeneralSettings::first();
        });

        return $settings->{$key} ?? $default;
    }

    // ✅ Update a setting value
    public function updateSetting($key, $value)
    {
        $settings = GeneralSettings::first();

        if (!$settings) {
            $settings = new GeneralSettings();
        }

        $settings->{$key} = $value;
        $settings->save();

        Cache::forget($this->cacheKey); // Clear cache after update
        return $settings;
    }

    // ✅ Get all settings
    public function getAllSettings()
    {
        return Cache::rememberForever($this->cacheKey, function () {
            return GeneralSettings::first();
        });
    }
}