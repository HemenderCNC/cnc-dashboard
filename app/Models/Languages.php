<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Languages extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'languages';
    protected $fillable = ['name'];

}
