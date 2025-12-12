<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;

class UsuarioController extends Controller
{
    /**
     * GET /api/usuarios
     * Lista todos los usuarios.
     * IMPORTANTE: para no exponer el hash, asegúrate de tener
     *   protected $hidden = ['password_hash'];
     * en el modelo Usuario. Aquí devolvemos tal cual el Eloquent Collection.
     */
    public function index()
    {
        // Devuelve JSON con la colección ordenada por id ascendente
        return response()->json(Usuario::orderBy('id')->get());
    }

    /**
     * POST /api/usuarios
     * Crea un usuario nuevo.
     * Campos:
     *  - full_name: string obligatorio
     *  - email: único en tabla 'usuarios'
     *  - rol: 'admin' | 'user'
     *  - activo: boolean
     *  - password: opcional; si no se envía se usa '1234'
     */
    public function store(Request $request)
    {
        // 1) Validación de entrada
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|unique:usuarios,email',
            'rol'       => ['required', Rule::in(['admin','user'])],
            'activo'    => 'required|boolean',
            'password'  => 'nullable|string|min:4',
        ]);

        // 2) Si no viene contraseña, forzamos '1234'
        $plain = $data['password'] ?? '1234';

        // 3) Construcción del modelo (el mutator en Usuario hashea password_hash)
        $usuario = new Usuario();
        $usuario->full_name     = $data['full_name'];
        $usuario->email         = strtolower($data['email']); // normalizamos a minúsculas
        $usuario->rol           = $data['rol'];
        $usuario->activo        = (bool)$data['activo'];
        $usuario->password_hash = $plain; // <- MUTATOR en el modelo debe aplicar Hash::make
        $usuario->save();

        // 4) Respuesta con el usuario creado (sin password_hash si está oculto en el modelo)
        return response()->json([
            'status'  => 'success',
            'message' => 'Usuario creado correctamente',
            'usuario' => $usuario,
        ], 201);
    }

    /**
     * PUT /api/usuarios/{id}
     * Actualiza datos de un usuario existente.
     * El email debe seguir siendo único (ignorando al propio id).
     * La contraseña es opcional: si viene, se actualiza; si no, no se toca.
     */
    public function update(Request $request, int $id)
    {
        // 1) Buscamos o 404
        $usuario = Usuario::findOrFail($id);

        // 2) Validaciones (email único ignorando el actual)
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => ['required','email',Rule::unique('usuarios','email')->ignore($id)],
            'rol'       => ['required', Rule::in(['admin','user'])],
            'activo'    => 'required|boolean',
            'password'  => 'nullable|string|min:4',
        ]);

        // 3) Actualización de campos "públicos"
        $usuario->full_name = $data['full_name'];
        $usuario->email     = strtolower($data['email']);
        $usuario->rol       = $data['rol'];
        $usuario->activo    = (bool)$data['activo'];

        // 4) Si llega password, actualizamos (mutator hashea)
        if (!empty($data['password'])) {
            $usuario->password_hash = $data['password']; // <- MUTATOR hashea
        }

        // 5) Persistimos cambios
        $usuario->save();

        // 6) Respuesta
        return response()->json([
            'status'  => 'success',
            'message' => 'Usuario actualizado correctamente',
            'usuario' => $usuario,
        ]);
    }

    /**
     * DELETE /api/usuarios/{id}
     * Elimina el usuario indicado.
     */
    public function destroy(int $id)
    {
        // 1) Buscamos o 404
        $usuario = Usuario::findOrFail($id);

        // 2) Eliminación (soft delete si el modelo lo usa; si no, hard delete)
        $usuario->delete();

        // 3) Respuesta
        return response()->json([
            'status'  => 'success',
            'message' => 'Usuario eliminado',
        ]);
    }

    /**
     * POST /api/usuarios/{id}/reset-password
     * Resetea la contraseña a '1234' (uso típico: acción de admin).
     * El mutator del modelo hashea el valor.
     */
    public function resetPassword(int $id)
    {
        // 1) Buscamos o 404
        $usuario = Usuario::findOrFail($id);

        // 2) Seteamos '1234' (mutator aplica hash)
        $usuario->password_hash = '1234';
        $usuario->save();

        // 3) Respuesta
        return response()->json([
            'status'  => 'success',
            'message' => 'Contraseña restablecida a 1234',
        ]);
    }

    /**
     * POST /api/usuarios/{id}/change-password
     * Cambia la contraseña desde el modal del login.
     * Espera:
     *  - password: string min 4
     * (Opcionalmente podrías pedir confirmación 'password_confirmation'
     *  y usar regla 'confirmed' si quieres doble validación.)
     */
    public function changePassword(Request $request, int $id)
    {
        // 1) Validación mínima (puedes usar 'confirmed' si envías confirmación)
        $data = $request->validate([
            'password' => 'required|string|min:4',
            // 'password' => 'required|string|min:4|confirmed', // si mandas password_confirmation
        ]);

        // 2) Buscamos o 404
        $usuario = Usuario::findOrFail($id);

        // 3) Asignamos nueva contraseña (mutator hashea)
        $usuario->password_hash = $data['password'];
        $usuario->save();

        // 4) Respuesta
        return response()->json([
            'status'  => 'success',
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }
}
