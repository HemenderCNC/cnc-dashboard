<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use Illuminate\Support\Str;

class PersonalAccessToken extends Eloquent
{
    protected $connection = 'mongodb'; // MongoDB connection
    protected $collection = 'personal_access_tokens'; // Collection name

    protected $fillable = [
        'tokenable_type', 'tokenable_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public static function createToken($tokenable, $name, $abilities = ['*'], $expiresInMinutes = 800)
    {
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        self::create([
            'tokenable_type' => get_class($tokenable),
            'tokenable_id' => $tokenable->id,
            'name' => $name,
            'token' => $hashedToken,
            'abilities' => $abilities,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);

        return $plainToken;
    }
}
