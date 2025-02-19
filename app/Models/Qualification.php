<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;


class Qualification extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb'; // Use MongoDB connection
    protected $collection = 'qualifications'; // Collection name
    protected $fillable = ['name']; // Allow mass assignment
}
