<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Usuario extends Model
{
    // Nombre real de la tabla
    protected $table = 'usuarios';

    // Si tu tabla no tiene created_at ni updated_at
    public $timestamps = false;

    // Campos rellenables masivamente
    protected $fillable = [
        'full_name',
        'email',
        'rol',
        'activo',
        'password_hash',
        'fecha_alta',
    ];

    // Ocultar el hash en las respuestas JSON
    protected $hidden = ['password_hash'];

    // Cast automático
    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Mutator: al asignar cualquier valor a password_hash,
     * se guarda automáticamente hasheado.
     */
    public function setPasswordHashAttribute($value)
    {
        if (!$value) {
            return;
        }

        // Si no parece ya un hash bcrypt, lo hasheamos
        if (!str_starts_with($value, '$2y$')) {
            $this->attributes['password_hash'] = Hash::make($value);
        } else {
            $this->attributes['password_hash'] = $value;
        }
    }
}
