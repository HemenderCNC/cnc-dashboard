<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class EmployeeStatus extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'employee_statuses';
    protected $fillable = ['name'];
    public function users()
    {
        return $this->hasMany(User::class, 'employee_status_id');
    }
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($WorkLocation) {
            // Remove department_id from users when the department is deleted
            User::where('employee_status_id', $WorkLocation->_id)->update(['employee_status_id' => null]);
        });
    }
}
