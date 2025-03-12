<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class ProjectFiles extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'project_files';

    protected $fillable = [
        'project_id',
        'document_name',
        'document',
        'created_by',
    ];
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', '_id');
    }
}
