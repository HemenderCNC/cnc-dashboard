<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Notice extends Eloquent
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'notices';
    
    protected $fillable = [
        'message',
        'start_date',
        'end_date',
        'status',
        'posted_by'
    ];
}
