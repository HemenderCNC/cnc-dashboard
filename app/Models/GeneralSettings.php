<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class GeneralSettings extends Eloquent
{
    use HasFactory;
    protected $collection = 'general_settings';
    protected $fillable = ['site_title', 'logo', 'leave_settings','created_by'];
    protected $casts = [
        'logo' => 'array',
        'leave_settings' => 'array',
    ];
}