<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tareas_catalogo', function (Blueprint $table) {
            // crea created_at y updated_at (NULL por defecto)
            if (!Schema::hasColumn('tareas_catalogo', 'created_at')) {
                $table->timestamps();
            }
            // borrado lógico (histórico)
            if (!Schema::hasColumn('tareas_catalogo', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tareas_catalogo', function (Blueprint $table) {
            if (Schema::hasColumn('tareas_catalogo', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('tareas_catalogo', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
