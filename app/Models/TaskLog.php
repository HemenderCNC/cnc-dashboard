<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class TaskLog extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'task_logs';

    protected $fillable = [
        'task_id',
        'field_name',
        'old_value',
        'new_value',
        'updated_user_id',
        'created_at'
    ];
}
