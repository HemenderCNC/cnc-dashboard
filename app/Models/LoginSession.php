<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class LoginSession extends Eloquent
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'login_sessions';

    protected $fillable = [
        'employee_id',
        'date',
        'time_log',
        'break',
        'break_log',
    ];
}
