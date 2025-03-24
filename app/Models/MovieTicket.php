<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class MovieTicket extends Eloquent
{
    protected $connection = 'mongodb';
    protected $fillable = ['image', 'date', 'amount','created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', '_id');
    }
}
