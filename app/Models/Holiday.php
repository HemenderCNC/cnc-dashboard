<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Holiday extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'holidays';
    protected $fillable = [
        'festival_name',
        'festival_date',
        'color',
        'festival_image',
        'greeting_message',
        'posted_by'
    ];
}
