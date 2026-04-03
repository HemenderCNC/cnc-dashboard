<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use App\Models\User;
use App\Models\Tasks;

class Overtime extends Eloquent
{
    protected $connection = 'mongodb'; // Specify MongoDB connection
    protected $collection = 'overtimes'; // Specify the collection name
    protected $fillable = ['employee_id', 'date', 'task_id', 'shift_hours', 'working_hours', 'ot_hours', 'reason', 'url', 'status']; // Specify the fillable fields
    
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', '_id');
    }

    public function task()
    {
        return $this->belongsTo(Tasks::class, 'task_id', '_id');
    }
}