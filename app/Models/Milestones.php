<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Milestones extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb'; // Specify MongoDB connection
    protected $collection = 'milestones'; // Collection name
    protected $fillable = ['name','project_id','start_date','end_date','color','status','created_by']; // Allow mass assignment


    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', '_id');
    }

}
