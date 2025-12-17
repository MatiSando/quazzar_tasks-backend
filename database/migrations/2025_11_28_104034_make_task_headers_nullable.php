<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas_chasis', function (Blueprint $t) {
            $t->string('bastidor')->nullable()->change();
        });

        Schema::table('tareas_montaje', function (Blueprint $t) {
            $t->string('bastidor')->nullable()->change();
            $t->string('color')->nullable()->change();
        });

        Schema::table('tareas_pintura', function (Blueprint $t) {
            $t->string('color')->nullable()->change();
            $t->string('RAL')->nullable()->change();
        });

        Schema::table('tareas_premontaje', function (Blueprint $t) {
            $t->string('color')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Si quieres revertir a NOT NULL, aquí iría el código
    }
};
