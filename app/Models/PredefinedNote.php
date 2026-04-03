<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;


class PredefinedNote extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'predefined_notes';
    protected $fillable = ['note'];
   
}