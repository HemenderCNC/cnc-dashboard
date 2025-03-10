<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class IndustryTypes extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'industry_types';
    protected $fillable = ['name'];

}
