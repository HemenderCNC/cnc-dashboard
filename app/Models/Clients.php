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
        'client_id', // Add client_id to the fillable array
    ];
    public static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            // Generate the client ID
            $lastClient = self::orderBy('_id', 'desc')->first();
            $lastId = $lastClient ? intval(substr($lastClient->client_id, 4)) : 0;
            $client->client_id = 'CLI-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        });
    }
    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
