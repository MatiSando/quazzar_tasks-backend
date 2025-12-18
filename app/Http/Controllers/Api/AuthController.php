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
        // 0) Leer el payload de forma robusta (JSON / form / raw)
        $payload = $request->json()->all();

        if (empty($payload)) {
            $payload = $request->all();
        }

        if (empty($payload)) {
            $raw = $request->getContent();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        // 1) Validación (sobre $payload, no sobre $request)
        $validator = \Illuminate\Support\Facades\Validator::make($payload, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
                // ✅ DEBUG TEMPORAL (para cerrar el caso y luego lo quitas)
                'debug' => [
                    'content_type' => $request->header('Content-Type'),
                    'accept'       => $request->header('Accept'),
                    'raw_len'      => strlen($request->getContent()),
                ],
            ], 422);
        }

        $data = $validator->validated();

        // 2) Búsqueda del usuario por email (normalizado en minúsculas)
        $user = Usuario::where('email', strtolower($data['email']))->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // 3) Comprobación de contraseña con soporte "legacy"
        $stored = (string) $user->password_hash;
        $plain  = (string) $data['password'];

        $ok = false;

        if (str_starts_with($stored, '$2y$')) {
            $ok = Hash::check($plain, $stored);
        } else {
            if (hash_equals($stored, $plain)) {
                $ok = true;
                $user->password_hash = $plain; // mutator aplica bcrypt
                $user->save();
            }
        }

        if (!$ok) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Contraseña incorrecta',
            ], 401);
        }

        if ((int)$user->activo === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Usuario inactivo',
            ], 403);
        }

        // 5) Token simple (como lo tenías)
        $token = base64_encode(random_bytes(32));

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
