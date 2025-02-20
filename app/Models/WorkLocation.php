<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class WorkLocation extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'work_locations';
    protected $fillable = ['name'];
    public function users()
    {
        return $this->hasMany(User::class, 'work_location_id');
    }
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($WorkLocation) {
            // Remove department_id from users when the department is deleted
            User::where('work_location_id', $WorkLocation->_id)->update(['work_location_id' => null]);
        });
    }
}
