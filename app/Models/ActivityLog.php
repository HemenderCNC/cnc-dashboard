<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class ActivityLog extends Eloquent
{
    protected $collection = 'activity_logs'; // MongoDB collection name

    protected $fillable = [
        'user',
        'action',
        'model',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent'
    ];
}
