<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FillTareasCatalogoFromAreasSeeder extends Seeder
{
    /** columnas de área que no se consideran “piezas” */
    private array $ignorar = [
        'id','color','ral','RAL','bastidor',
        'fecha_inicio','fecha_fin','estado',
        'created_at','updated_at','deleted_at'
    ];

    private function prettify(string $col): string
    {
        $s = str_replace('_',' ', $col);
        // Capitaliza la primera letra, resto minúsculas (simple)
        return mb_strtoupper(mb_substr($s,0,1)).mb_strtolower(mb_substr($s,1));
    }

    private function insertFrom(string $tabla, string $proceso): void
    {
        $cols = DB::select("
          SELECT COLUMN_NAME AS name
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        ", [$tabla]);

        foreach ($cols as $c) {
            $col = $c->name;
            if (in_array($col, $this->ignorar, true)) continue;

            $label = $this->prettify($col);

            $exists = DB::table('tareas_catalogo')
                ->where('proceso', $proceso)
                ->where('label',   $label)
                ->exists();

            if (!$exists) {
                DB::table('tareas_catalogo')->insert([
                    'proceso'    => $proceso,
                    'seccion'    => null,
                    'label'      => $label,
                    'activa'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function run(): void
    {
        $this->insertFrom('tareas_chasis',     'chasis');
        $this->insertFrom('tareas_pintura',    'pintura');
        $this->insertFrom('tareas_premontaje', 'premontaje');
        $this->insertFrom('tareas_montaje',    'montaje');
    }
}
