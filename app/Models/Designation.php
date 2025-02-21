<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Designation extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb'; // Specify MongoDB connection
    protected $collection = 'designations'; // Collection name
    protected $fillable = ['name','department_id']; // Allow mass assignment
    protected $appends = ['department_name'];
    /**
     * Define relationship with Department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', '_id');
    }

    /**
     * Accessor to get department name dynamically
     */
    public function getDepartmentNameAttribute()
    {
        return $this->department ? $this->department->name : null;
    }
}
