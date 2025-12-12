<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/login
     * Autentica un usuario por email + password.
     * Flujo:
     *  1) Valida entrada.
     *  2) Busca usuario por email (normalizado a minúsculas).
     *  3) Comprueba contraseña:
     *     - Si el hash en BD es bcrypt ($2y$...), usa Hash::check.
     *     - Si quedó en texto plano (legado), compara en claro y, si coincide,
     *       actualiza a hash usando el mutator del modelo.
     *  4) Verifica que el usuario esté activo.
     *  5) Genera un token aleatorio (no se persiste aquí; el front lo guarda en sessionStorage).
     *  6) Devuelve JSON con status + token + datos públicos del usuario.
     */
    public function login(Request $request)
    {
        // 1) Reglas de validación básicas para el body del login
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // 2) Búsqueda del usuario por email (normalizado en minúsculas)
        //    Importante: en migraciones/seeders, almacenar emails ya en lowercase.
        $user = Usuario::where('email', strtolower($request->email))->first();

        // Si no existe el usuario, devolvemos 404 (no encontrado)
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // 3) Comprobación de contraseña con soporte "legacy"
        //    $stored: lo que hay en la BD (puede ser hash bcrypt o texto plano heredado)
        //    $plain:  lo que llega del login
        $stored = (string) $user->password_hash;
        $plain  = (string) $request->password;

        $ok = false;

        if (str_starts_with($stored, '$2y$')) {
            // Caso moderno: el hash es bcrypt => usar Hash::check
            $ok = Hash::check($plain, $stored);
        } else {
            // Caso legado: quedó contraseña en texto plano (migraciones antiguas)
            // Comparamos en claro y, si coincide, "actualizamos" a hash
            // aprovechando el mutator del modelo (setPasswordHashAttribute).
            if (hash_equals($stored, $plain)) {
                $ok = true;
                $user->password_hash = $plain; // el mutator aplica bcrypt automáticamente
                $user->save();
            }
        }

        // Si la contraseña no es correcta, devolvemos 401 (no autorizado)
        if (!$ok) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Contraseña incorrecta',
            ], 401);
        }

        // 4) Chequeo de estado activo: si está inactivo => 403 (prohibido)
        if ((int)$user->activo === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Usuario inactivo',
            ], 403);
        }

        // 5) Emisión de token de sesión "simple" para el front
        //    NOTA: Aquí se genera pero no se guarda en BD; el front lo pone en sessionStorage
        //    y lo envía como Bearer con el interceptor. Si quieres revocar/expirar, persístelo.
        $token = base64_encode(random_bytes(32));

        // 6) Respuesta con datos públicos del usuario (no incluir hash)
        return response()->json([
            'status'  => 'success',
            'message' => 'Login correcto',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'full_name' => $user->full_name,
                'email'     => $user->email,
                'rol'       => $user->rol,
            ],
        ]);
    }
}
