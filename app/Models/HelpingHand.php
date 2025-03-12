<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class HelpingHand extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'helping_hands';

    protected $fillable = [
        'from_id',
        'to_id',
        'project_id',
        'issue',
        'status',
        'schedule_time',
        'time_log',
    ];
    public function from()
    {
        return $this->belongsTo(User::class, 'from_id', '_id');
    }
    public function to()
    {
        return $this->belongsTo(User::class, 'to_id', '_id');
    }
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', '_id');
    }
}
