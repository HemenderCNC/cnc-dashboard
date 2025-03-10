<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Countries extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'countries';
    protected $fillable = ['name'];

}
