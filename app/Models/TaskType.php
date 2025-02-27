<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class TaskType extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'task_types';

    protected $fillable = [
        'name',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($tasktype) {
            Tasks::where('task_type_id', $tasktype->_id)->update(['task_type_id' => null]);
        });
    }
}
