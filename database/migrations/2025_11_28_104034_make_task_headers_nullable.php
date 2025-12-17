<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tareas_chasis')) {
            Schema::table('tareas_chasis', function (Blueprint $t) {
                if (Schema::hasColumn('tareas_chasis', 'bastidor')) {
                    $t->string('bastidor')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('tareas_montaje')) {
            Schema::table('tareas_montaje', function (Blueprint $t) {
                if (Schema::hasColumn('tareas_montaje', 'bastidor')) {
                    $t->string('bastidor')->nullable()->change();
                }
                if (Schema::hasColumn('tareas_montaje', 'color')) {
                    $t->string('color')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('tareas_pintura')) {
            Schema::table('tareas_pintura', function (Blueprint $t) {
                if (Schema::hasColumn('tareas_pintura', 'color')) {
                    $t->string('color')->nullable()->change();
                }
                if (Schema::hasColumn('tareas_pintura', 'RAL')) {
                    $t->string('RAL')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('tareas_premontaje')) {
            Schema::table('tareas_premontaje', function (Blueprint $t) {
                if (Schema::hasColumn('tareas_premontaje', 'color')) {
                    $t->string('color')->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        // opcional
    }
};
