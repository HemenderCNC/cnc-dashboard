<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class ProjectStatus extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'project_statuses';
    protected $fillable = ['name'];
    public function users()
    {
        return $this->hasMany(User::class, 'project_status_id');
    }
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($WorkLocation) {
            // Remove department_id from users when the department is deleted
            User::where('project_status_id', $WorkLocation->_id)->update(['project_status_id' => null]);
        });
    }
}
