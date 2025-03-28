<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Platform extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'platforms';
    protected $fillable = ['name'];

}
