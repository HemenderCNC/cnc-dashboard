<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Designation extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb'; // Specify MongoDB connection
    protected $collection = 'designations'; // Collection name
    protected $fillable = ['name']; // Allow mass assignment
}
