<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Clients extends Eloquent
{
    use Notifiable;
    protected $connection = 'mongodb';
    protected $fillable = [
        'company_name',
        'first_name',
        'last_name',
        'about_client',
        'client_type',
        'profile_photo',
        'country',
        'state',
        'city',
        'industry_type',
        'status',
        'client_priority',
        'preferred_communication',
        'client_notes',
        'referral_source',
        'account_manager_id',
        'created_by',
    ];
    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
