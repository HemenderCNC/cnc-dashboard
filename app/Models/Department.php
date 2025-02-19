<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Department extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'departments';
    protected $fillable = ['name','description'];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($department) {
            // Remove department_id from users when the department is deleted
            User::where('department_id', $department->_id)->update(['department_id' => null]);
        });
    }
}
