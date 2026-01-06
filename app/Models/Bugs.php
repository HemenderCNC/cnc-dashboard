<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Bugs extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'bugs';
    protected $fillable = ['user_id', 'title', 'description', 'link', 'status', 'media', 'module', 'type', 'priority'];

    protected $casts = [
        'media' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public $timestamps = true;
   // Relationship with Users
   public function users()
   {
       return $this->belongsTo(User::class, 'user_id');
   }
}