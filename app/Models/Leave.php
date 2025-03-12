<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Leave extends Eloquent
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'leaves';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'leave_duration',
        'half_day',
        'half_day_type',
        'reason',
        'status',
        'leave_type',
        'approve_comment',
        'approved_by'
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class, 'employee_id');
    }
}
