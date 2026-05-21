<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class LeaveBalance extends Eloquent
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'leave_balances';

    protected $fillable = [
        'user_id',
        'privilege_leave',
        'paternity_leave',
        'critical_medical_leave',
        'leave_without_pay',
        'year',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
