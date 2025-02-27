<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Department extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'departments';
    protected $fillable = ['name','description'];

    // Relationship with Users
    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    // Relationship with Designations
    public function designations()
    {
        return $this->hasMany(Designation::class, 'department_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($department) {
            User::where('department_id', $department->_id)->update(['department_id' => null]);
            Designation::where('department_id', $department->_id)->delete();
        });
    }
}
