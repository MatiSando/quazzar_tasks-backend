<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = [
        'full_name',
        'email',
        'rol',
        'activo',
        'password_hash',
        'fecha_alta',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ✅ Laravel Auth necesita saber cuál es la “password”
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function setPasswordHashAttribute($value)
    {
        if (!$value) return;

        if (!str_starts_with($value, '$2y$')) {
            $this->attributes['password_hash'] = Hash::make($value);
        } else {
            $this->attributes['password_hash'] = $value;
        }
    }
}
