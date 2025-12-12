<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
// Opcional: si prefieres usar Schema:: en vez de \Schema:
use Illuminate\Support\Facades\Schema;

class TareasController extends Controller
{
    /**
     * Resuelve el nombre de la tabla de área a partir del slug recibido.
     * Lanza excepción si el área no existe.
     */
    private function tablaArea(string $area): string {
        return match (strtolower($area)) {
            'pintura'    => 'tareas_pintura',
            'chasis'     => 'tareas_chasis',
            'premontaje' => 'tareas_premontaje',
            'montaje'    => 'tareas_montaje',
            default      => throw new \InvalidArgumentException('Área no válida'),
        };
    }

    /**
     * POST /tareas/iniciar
     * Crea o reusa un pendiente (por VIN) y registra el vínculo en tabla global "tareas".
     * - Si llega "bastidor" y ya existe una tarea PENDIENTE con ese VIN en el área,
     *   la actualiza (si no está “bloqueada” por otro usuario).
     * - Si no existe, crea una nueva.
     * Body esperado:
     *  {
     *    usuario_id,              // requerido (existe en usuarios)
     *    area: 'pintura|chasis|premontaje|montaje',
     *    color?: string,
     *    RAL?: string,            // sólo se usa si el área es pintura
     *    bastidor?: string,
     *    checks?: { 'Label tarea' : true/false, ... }
     *  }
     * Respuesta:
     *  { status: 'success', area, id_area }  | 423 locked_by_other
     */
    public function iniciar(Request $req) {
        // 1) Validación de parámetros de entrada
        $data = $req->validate([
            'usuario_id' => ['required','integer','exists:usuarios,id'],                     // OJO: tu tabla de usuarios es "usuarios"
            'area'       => ['required', Rule::in(['pintura','chasis','premontaje','montaje'])],
            'color'      => ['nullable','string'],
            'RAL'        => ['nullable','string'],
            'bastidor'   => ['nullable','string'],
            'checks'     => ['nullable','array'],
        ]);

        // 2) Determinamos la tabla destino según el área
        $tabla = $this->tablaArea($data['area']);

        // 3) Obtenemos las columnas reales de la tabla (para filtrar las columnas de checks válidas)
        $cols = Schema::getColumnListing($tabla);         // equivalente a \Schema::getColumnListing
        $colsSet = array_flip($cols);                     // set para O(1) en "isset"

        // 4) Normalizador de labels -> nombre de columna (coincide con tu front)
        $norm = function(string $label): string {
            // Pasa a ASCII para quitar tildes/acentos
            $ascii = iconv('UTF-8','ASCII//TRANSLIT',$label);
            $ascii = $ascii === false ? $label : $ascii;
            // minúsculas + no alfanumérico -> _
            $s = strtolower($ascii);
            $s = preg_replace('/[^a-z0-9]+/', '_', $s);
            return trim($s, '_');
        };

        // =======================================================
        // 5) Buscar si ya existe pendiente previo por el mismo VIN
        //    (Sólo se busca si llega 'bastidor')
        // =======================================================
        $prev = null;
        if ($req->filled('bastidor')) {
            $vin = strtoupper(trim($req->input('bastidor')));

            $prev = DB::table($tabla)
                ->where('estado', 'pendiente')
                ->where('bastidor', $vin)
                ->orderByDesc('id')
                ->first();
        }

        // =======================================================
        // 6) Si hay pendiente, comprobar que pertenezca al mismo usuario
        //    (Se consulta la tabla global "tareas" para ver propietario)
        // =======================================================
        if ($prev) {
            $ownerRow = DB::table('tareas')
                ->where('tabla_area', $tabla)
                ->where('id_tarea_area', $prev->id)
                ->orderBy('id', 'asc')
                ->first();

            $ownerId = $ownerRow->id_usuario ?? null;
            if ($ownerId !== null && $ownerId !== $data['usuario_id']) {
                // 423 Locked: otro usuario es el dueño de la tarea pendiente de este VIN
                return response()->json([
                    'status'   => 'locked_by_other',
                    'area'     => $data['area'],
                    'id_area'  => (int)$prev->id,
                    'owner_id' => (int)$ownerId,
                ], 423);
            }
        }

        // =======================================================
        // 7) Construir payload a guardar/actualizar en el área
        // =======================================================
        $toWrite = [
            'fecha_fin' => null,                 // por iniciar/continuar pendiente
            'estado'    => 'pendiente',
        ];

        // Cadenas principales (normalizadas)
        if ($req->filled('bastidor')) $toWrite['bastidor'] = strtoupper(trim($req->input('bastidor')));
        if ($req->filled('color'))    $toWrite['color']    = trim($req->input('color'));
        if ($req->filled('RAL'))      $toWrite['RAL']      = trim($req->input('RAL')); // en tablas que la tengan

        // Checks: del objeto { label: bool } sólo usamos columnas válidas en tabla
        if (is_array($req->input('checks'))) {
            foreach ($req->input('checks') as $label => $val) {
                if (!is_string($label)) continue;
                $col = $norm($label);
                if (isset($colsSet[$col])) {
                    // Convertimos cualquier truthy a 1; falsy a 0
                    $toWrite[$col] = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                }
            }
        }

        // =======================================================
        // 8) Crear o actualizar registro en la tabla de área
        // =======================================================
        if ($prev) {
            // Si existía, actualizamos ese mismo ID
            DB::table($tabla)->where('id', $prev->id)->update($toWrite);
            $idArea = (int)$prev->id;
        } else {
            // Si no existía, insertamos nuevo (fecha_inicio ahora)
            $idArea = DB::table($tabla)->insertGetId(array_merge([
                'fecha_inicio' => now(),
            ], $toWrite));
        }

        // =======================================================
        // 9) Registrar vínculo en tabla global "tareas" (si no existe)
        //    Esto sirve para:
        //    - Saber el propietario de la tarea (id_usuario)
        //    - Listar "pendientesUsuario" sin joins complejos
        // =======================================================
        $existsLog = DB::table('tareas')
            ->where('id_usuario', $data['usuario_id'])
            ->where('tabla_area', $tabla)
            ->where('id_tarea_area', $idArea)
            ->exists();

        if (!$existsLog) {
            DB::table('tareas')->insert([
                'id_usuario'     => $data['usuario_id'],
                'id_tarea_area'  => $idArea,
                'tabla_area'     => $tabla,
                'fecha_creacion' => now()->toDateString(),
            ]);
        }

        // 10) Devolvemos OK con el id_area para que el front pueda finalizar/actualizar por ID
        return response()->json([
            'status'   => 'success',
            'area'     => $data['area'],
            'id_area'  => $idArea,
        ]);
    }

    /**
     * PUT /tareas/{area}/{id}
     * Actualiza columnas de la tarea por ID (color, RAL, checks, etc.)
     * No fuerza estado; es update parcial.
     * Seguridad básica: convierte boolean a 1/0.
     */
    public function actualizar(Request $req, string $area, int $id) {
        $tabla = $this->tablaArea($area);

        // Opcional: validar que el registro exista antes de update (para responder 404)
        // if (!DB::table($tabla)->where('id',$id)->exists()) return response()->json(['status'=>'not_found'],404);

        $upd   = [];
        foreach ($req->all() as $k => $v) {
            if (is_string($k)) {
                // Convertimos booleanos a 1/0 para guardar en tinyint
                $upd[$k] = is_bool($v) ? ($v ? 1 : 0) : $v;
            }
        }

        if (empty($upd)) return response()->json(['status' => 'noop']); // nada que actualizar

        DB::table($tabla)->where('id', $id)->update($upd);
        return response()->json(['status' => 'success']);
    }

    /**
     * POST /tareas/{area}/{id}/finalizar
     * Marca la tarea como finalizada y establece la fecha_fin.
     */
    public function finalizar(string $area, int $id) {
        $tabla = $this->tablaArea($area);

        DB::table($tabla)->where('id',$id)->update([
            'estado'    => 'finalizada',
            'fecha_fin' => now(),
        ]);

        return response()->json(['status'=>'success']);
    }

    /**
     * POST /tareas/{area}/{id}/pendiente
     * Permite volver a estado 'pendiente' (reabrir) y limpia fecha_fin.
     * Útil si reanudan una tarea que cerraron por error.
     */
    public function pendiente(string $area, int $id) {
        $tabla = $this->tablaArea($area);

        DB::table($tabla)->where('id',$id)->update([
            'estado'    => 'pendiente',
            'fecha_fin' => null,
        ]);

        return response()->json(['status'=>'success']);
    }

    /**
     * GET /bastidores/chasis
     * Devuelve hasta 1000 VIN válidos (17 chars alfanuméricos) únicos y normalizados.
     * Con cache-control desactivado para evitar “stale” en el front.
     */
    public function bastidoresChasis() {
        $bastidores = DB::table('tareas_chasis')
            ->whereNotNull('bastidor')
            ->where('bastidor', '<>', '')
            ->orderByDesc('id')
            ->limit(1000)
            ->pluck('bastidor')
            ->map(fn ($v) => strtoupper(trim($v)))
            ->filter(fn ($v) => preg_match('/^[A-Z0-9]{17}$/', $v))
            ->unique()
            ->values();

        return response()->json($bastidores, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * GET /montaje/vin-disponible/{vin}
     * Dice si un VIN ya fue FINALIZADO en montaje (para bloquear duplicados).
     */
    public function vinDisponibleMontaje(string $vin) {
        $vin = strtoupper(trim($vin));

        $yaUsado = DB::table('tareas_montaje')
            ->where('bastidor', $vin)
            ->where('estado', 'finalizada')
            ->exists();

        return response()->json(['available' => !$yaUsado], 200);
    }

    /**
     * GET /pintura/colores
     * Lista de colores únicos (máximo 500), normalizados, sin caché.
     * Útil para selector de color en Premontaje/Pintura.
     */
    public function coloresPintura() {
        $list = DB::table('tareas_pintura')
            ->whereNotNull('color')
            ->where('color', '<>', '')
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('color')
            ->map(fn($v) => trim((string)$v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->values();

        return response()->json($list, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * GET /pintura/color-por-vin/{vin}
     * Trae el último color (y RAL si existe) usado para ese VIN en Pintura.
     * Soporta tablas sin columna RAL (busca 'RAL' o 'ral').
     */
    public function colorPinturaPorVin(string $vin) {
        $vin = strtoupper(trim($vin));
        $tabla = 'tareas_pintura';

        $cols  = Schema::getColumnListing($tabla);
        $hasBastidor = in_array('bastidor', $cols, true);
        $hasColor    = in_array('color',    $cols, true);
        $hasRAL      = in_array('RAL',      $cols, true) || in_array('ral', $cols, true);

        if (!$hasColor) return response()->json(['color' => null, 'RAL' => null], 200);

        $select = ['color'];
        if ($hasRAL) $select[] = in_array('RAL', $cols, true) ? 'RAL' : 'ral';

        $q = DB::table($tabla)->orderByDesc('id')->select($select);
        if ($hasBastidor) $q->where('bastidor', $vin);

        $row = $q->first();

        return response()->json([
            'color' => $row->color ?? null,
            'RAL'   => $row->RAL   ?? ($row->ral ?? null),
        ], 200);
    }

    /**
     * GET /{area}/pendiente-por-vin/{vin}?user_id=123
     * Reanuda sólo SI la pendiente de ese VIN pertenece a ese usuario (si se pasa user_id).
     * Devuelve { exists, id, bastidor, color, RAL?, checks{} }.
     */
    public function pendientePorVin(Request $req, string $area, string $vin) {
        $tabla  = $this->tablaArea($area);
        $vin    = strtoupper(trim($vin));
        $userId = (int) $req->query('user_id', 0);

        $columnas = Schema::getColumnListing($tabla);
        $excluir  = ['id','color','RAL','bastidor','fecha_inicio','fecha_fin','estado'];

        // Última tarea pendiente por VIN
        $row = DB::table($tabla)
            ->where('bastidor', $vin)
            ->where('estado', 'pendiente')
            ->orderByDesc('id')
            ->first();

        if (!$row) return response()->json(['exists' => false], 200);

        // Si se exige que sea del propio usuario:
        if ($userId > 0) {
            $esDelUsuario = DB::table('tareas')
                ->where('id_usuario', $userId)
                ->where('tabla_area', $tabla)
                ->where('id_tarea_area', $row->id)
                ->exists();

            if (!$esDelUsuario) return response()->json(['exists' => false], 200);
        }

        // Mapear checks (todas las columnas desconocidas -> se consideran “checks”)
        $checks = [];
        foreach ($columnas as $col) {
            if (in_array($col, $excluir, true)) continue;
            $checks[$col] = !empty($row->$col);
        }

        return response()->json([
            'exists'    => true,
            'id'        => (int)$row->id,
            'bastidor'  => $row->bastidor ?? null,
            'color'     => $row->color    ?? null,
            'RAL'       => $row->RAL      ?? null,
            'checks'    => $checks,
        ], 200);
    }

    /**
     * GET /chasis/vin-estado/{vin}
     * Devuelve estado del VIN en Chasis:
     *  - free: no hay registros
     *  - finalized: el último está finalizado (bloquear)
     *  - pending: hay uno pendiente + devuelve checks
     */
    public function vinEstadoChasis(string $vin) {
        $vin = strtoupper(trim($vin));
        $tabla = 'tareas_chasis';

        $cols = Schema::getColumnListing($tabla);
        $excluir = ['id','color','RAL','bastidor','fecha_inicio','fecha_fin','estado'];

        $ultima = DB::table($tabla)
            ->where('bastidor', $vin)
            ->orderByDesc('id')
            ->first();

        if (!$ultima) return response()->json(['status' => 'free'], 200);
        if (($ultima->estado ?? '') === 'finalizada') return response()->json(['status' => 'finalized'], 200);

        if (($ultima->estado ?? '') === 'pendiente') {
            $checks = [];
            foreach ($cols as $c) {
                if (in_array($c, $excluir, true)) continue;
                $checks[$c] = !empty($ultima->$c);
            }
            return response()->json([
                'status' => 'pending',
                'id'     => $ultima->id,
                'checks' => $checks,
            ], 200);
        }

        return response()->json(['status' => 'free'], 200);
    }

    /**
     * GET /tareas/log?from=YYYY-MM-DD&to=YYYY-MM-DD&trabajador=...&area=...
     * Devuelve un “log” plano leyendo la tabla global "tareas"
     * y consultando fecha_fin en cada tabla de área.
     */
    public function tareasLog(Request $req) {
        $from       = trim((string) $req->query('from', ''));
        $to         = trim((string) $req->query('to', ''));
        $trabajador = trim((string) $req->query('trabajador', ''));
        $area       = strtolower(trim((string) $req->query('area', '')));

        $q = DB::table('tareas as t')
            ->join('usuarios as u', 'u.id', '=', 't.id_usuario')
            ->select([
                't.id',
                't.fecha_creacion as fecha',
                'u.full_name as trabajador',
                't.tabla_area',
                't.id_tarea_area',
            ])
            ->orderByDesc('t.id');

        if ($from !== '') $q->where('t.fecha_creacion', '>=', $from);
        if ($to !== '')   $q->where('t.fecha_creacion', '<=', $to);
        if ($trabajador !== '') $q->where('u.full_name', 'like', "%{$trabajador}%");
        if (in_array($area, ['pintura','chasis','premontaje','montaje'], true)) {
            $q->where('t.tabla_area', "tareas_{$area}");
        }

        $rows = $q->limit(1000)->get();

        // Mapeo de tabla -> título amigable
        $mapArea = function ($tabla) {
            $slug = strtolower(str_replace('tareas_', '', (string)$tabla));
            return match ($slug) {
                'premontaje' => 'Premontaje',
                'montaje'    => 'Montaje',
                'pintura'    => 'Pintura',
                'chasis'     => 'Chasis',
                default      => '—',
            };
        };

        $out = [];
        foreach ($rows as $r) {
            // Buscamos fecha_fin en la tabla real de área (si existe)
            $fechaFin = null;
            if ($r->tabla_area && $r->id_tarea_area) {
                try {
                    $fechaFin = DB::table($r->tabla_area)
                        ->where('id', $r->id_tarea_area)
                        ->value('fecha_fin');
                } catch (\Throwable $e) {
                    // Si la tabla no existe o falla, dejamos null
                    $fechaFin = null;
                }
            }

            $out[] = [
                'id'         => (int)$r->id,
                'fecha'      => (string)$r->fecha,
                'fecha_fin'  => $fechaFin ? (string)$fechaFin : null,
                'trabajador' => (string)$r->trabajador,
                'area'       => $mapArea($r->tabla_area),
                'accion'     => 'Registro',
                'resultado'  => 'OK',
            ];
        }

        return response()->json($out, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * GET /pendientes/{usuarioId}
     * Devuelve todos los pendientes del usuario en todas las áreas,
     * con resumen de checks: total y hechos.
     */
    public function pendientesUsuario(int $usuarioId) {
        $tablasArea = ['tareas_premontaje','tareas_montaje','tareas_pintura','tareas_chasis'];

        // Leemos la tabla global "tareas" para saber vínculos (usuario -> tarea de área)
        $rows = DB::table('tareas')
            ->where('id_usuario', $usuarioId)
            ->orderByDesc('id')
            ->limit(1000)
            ->get(['id_tarea_area', 'tabla_area', 'fecha_creacion']);

        $pendientes = [];
        foreach ($rows as $r) {
            $tabla = $r->tabla_area;
            if (!in_array($tabla, $tablasArea, true)) continue;

            $cols    = Schema::getColumnListing($tabla);
            $excluir = ['id','color','RAL','bastidor','fecha_inicio','fecha_fin','estado'];

            $areaRow = DB::table($tabla)->where('id', $r->id_tarea_area)->first();
            if (!$areaRow) continue;
            if (($areaRow->estado ?? '') !== 'pendiente') continue;

            // Contamos checks (todas las columnas desconocidas se consideran checks)
            $total = 0; $done = 0;
            foreach ($cols as $c) {
                if (in_array($c, $excluir, true)) continue;
                $total++;
                if (!empty($areaRow->$c)) $done++;
            }

            $pendientes[] = [
                'area'          => ucfirst(str_replace('tareas_', '', $tabla)),
                'area_key'      => str_replace('tareas_', '', $tabla),
                'id'            => (int)$areaRow->id,
                'bastidor'      => $areaRow->bastidor ?? null,
                'color'         => $areaRow->color ?? null,
                'RAL'           => $areaRow->RAL ?? null,
                'fecha_inicio'  => $areaRow->fecha_inicio ?? null,
                'total_checks'  => $total,
                'done_checks'   => $done,
            ];
        }

        // Deduplicación por área+id (por si hay repetidos en log)
        $seen = [];
        $result = [];
        foreach ($pendientes as $p) {
            $k = $p['area_key'].'#'.$p['id'];
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $result[] = $p;
        }

        // Orden por fecha_inicio desc
        usort($result, fn($a,$b)=>strcmp($b['fecha_inicio'] ?? '', $a['fecha_inicio'] ?? ''));

        return response()->json($result);
    }

    /**
     * GET /snapshot/{area}/{id}
     * Devuelve snapshot completo de un pendiente por ID (para autorreanudación exacta).
     * Incluye estado, fechas y checks.
     */
    public function snapshotPendiente(string $area, int $id) {
        $tabla = $this->tablaArea($area);

        $cols = Schema::getColumnListing($tabla);
        $excluir = ['id','color','RAL','bastidor','fecha_inicio','fecha_fin','estado'];

        $row = DB::table($tabla)->where('id', $id)->first();
        if (!$row) return response()->json(['exists' => false], 404);

        // Mapear checks
        $checks = [];
        foreach ($cols as $c) {
            if (in_array($c, $excluir, true)) continue;
            $checks[$c] = !empty($row->$c);
        }

        return response()->json([
            'exists'       => true,
            'id'           => (int)$row->id,
            'estado'       => $row->estado ?? null,
            'bastidor'     => $row->bastidor ?? null,
            'color'        => $row->color ?? null,
            'RAL'          => $row->RAL ?? null,
            'fecha_inicio' => $row->fecha_inicio ?? null,
            'checks'       => $checks,
        ], 200);
    }

    /**
     * GET /{area}/finalizado-por-vin/{vin}
     * True si existe algún registro finalizado para ese VIN en el área.
     */
    public function finalizadoPorVin(string $area, string $vin) {
        $tabla = $this->tablaArea($area);
        $vin = strtoupper(trim($vin));

        $finalizado = DB::table($tabla)
            ->where('bastidor', $vin)
            ->where('estado','finalizada')
            ->exists();

        return response()->json(['finalized' => $finalizado]);
    }
}
