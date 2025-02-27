<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class TaskStatus extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'task_statuses';
    protected $fillable = ['name'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($taskstatus) {
            Tasks::where('status_id', $taskstatus->_id)->update(['status_id' => null]);
        });
    }
}
