<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('full_name');
            $t->string('email')->unique();
            $t->string('rol')->default('operario');
            $t->boolean('activo')->default(true);
            $t->string('password_hash');
            $t->dateTime('fecha_alta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
