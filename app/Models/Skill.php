<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;


class Skill extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb'; 
    protected $collection = 'skills'; 
    protected $fillable = ['name']; 
}
