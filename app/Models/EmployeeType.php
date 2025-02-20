<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;


class EmployeeType extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'employee_types';
    protected $fillable = ['name'];
    public function users()
    {
        return $this->hasMany(User::class, 'employment_type_id');
    }
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($EmployeeType) {
            // Remove department_id from users when the department is deleted
            User::where('employment_type_id', $EmployeeType->_id)->update(['employment_type_id' => null]);
        });
    }
}
