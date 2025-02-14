<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Options extends Eloquent
{
    protected $connection = 'mongodb'; // Specify MongoDB connection
    protected $collection = 'options'; // Specify the collection name
    protected $fillable = ['category', 'value', 'group']; // Specify the fillable fields
}
