<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TareaCatalogoController extends Controller
{
    /**
     * GET /api/tareas-catalogo
     * Lista las tareas del catálogo con filtros opcionales por:
     *   – proceso: pintura | chasis | premontaje | montaje
     *   – activa:  1|0
     * Retorna ordenado por proceso > sección > label.
     */
    public function index(Request $req)
    {
        // Select explícito para no traer más de la cuenta
        $q = DB::table('tareas_catalogo')->select('id','proceso','seccion','label','activa');

        // Filtro por proceso si viene en la query (?proceso=pintura)
        if ($req->filled('proceso')) {
            $q->where('proceso', $req->input('proceso'));
        }
        // Filtro por activa si viene en la query (?activa=1)
        if ($req->filled('activa')) {
            $q->where('activa', (int)$req->input('activa'));
        }

        // Orden estable (útil para el front)
        return response()->json(
            $q->orderBy('proceso')->orderBy('seccion')->orderBy('label')->get()
        );
    }

    /**
     * POST /api/tareas-catalogo
     * Crea una nueva fila del catálogo.
     * Validaciones:
     *  - proceso: enum fijo
     *  - seccion: opcional, máx 100
     *  - label:   requerido, máx 255
     *  - activa:  boolean
     * Devuelve 201 con el recurso creado (id + payload).
     */
    public function store(Request $req)
    {
        // Validación de entrada
        $data = $req->validate([
            'proceso' => ['required', Rule::in(['pintura','chasis','premontaje','montaje'])],
            'seccion' => ['nullable','string','max:100'],
            'label'   => ['required','string','max:255'],
            'activa'  => ['required','boolean'],
        ]);

        // Inserción y recuperación del id
        $id = DB::table('tareas_catalogo')->insertGetId($data);

        // Respuesta creada (201)
        return response()->json([
            'status' => 'success',
            'tarea'  => array_merge(['id'=>$id], $data),
        ], 201);
    }

    /**
     * PUT /api/tareas-catalogo/{id}
     * Actualiza una fila existente del catálogo.
     * Todos los campos son opcionales (partial update).
     * Si no hay cambios (payload vacío) => 'noop'.
     * Si no existe el id => 404.
     */
    public function update(Request $req, int $id)
    {
        // Validación: todos opcionales pero con reglas
        $data = $req->validate([
            'proceso' => [Rule::in(['pintura','chasis','premontaje','montaje'])],
            'seccion' => ['nullable','string','max:100'],
            'label'   => ['nullable','string','max:255'],
            'activa'  => ['nullable','boolean'],
        ]);

        // Si no llegó ningún campo válido, no hacemos nada
        if (empty($data)) {
            return response()->json(['status'=>'noop']);
        }

        // Intento de actualización
        $updated = DB::table('tareas_catalogo')->where('id',$id)->update($data);

        // Si no se actualizó ninguna fila, devolvemos 404
        if (!$updated) {
            return response()->json(['status'=>'error','message'=>'No encontrado'], 404);
        }

        return response()->json(['status'=>'success']);
    }

    /**
     * DELETE /api/tareas-catalogo/{id}
     * Elimina una fila del catálogo.
     * Si el id no existe => 404.
     */
    public function destroy(int $id)
    {
        $deleted = DB::table('tareas_catalogo')->where('id',$id)->delete();

        if (!$deleted) {
            return response()->json(['status'=>'error','message'=>'No encontrado'], 404);
        }
        return response()->json(['status'=>'success']);
    }
}
