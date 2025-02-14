<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'projects';
    
    protected $fillable = [
        'name',
        'industry',
        'type',
        'budget',
        'description',
        'platform_technology',
        'programming_languages',
        'priority',
        'status',
        'timeline' => [
            'estimated_start_date',
            'estimated_end_date',
            'actual_start_date',
            'actual_end_date',
        ],
        'stakeholders' => [
            'client',
            'assignees',
            'project_manager'
        ],
        'files',
        'milestones' => [
            'name',
            'start_date',
            'end_date',
            'color',
            'status'
        ]
    ];
}
