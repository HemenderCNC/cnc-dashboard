<?php

namespace App\Models;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
class Media extends Eloquent
{
    use HasFactory;
    use Notifiable;
    protected $connection = 'mongodb'; 
    protected $fillable = [
        'original_name',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];
}
