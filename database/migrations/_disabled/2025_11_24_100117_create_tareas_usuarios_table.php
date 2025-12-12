<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tareas_usuarios', function (Blueprint $t) {
      $t->id();
      $t->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
      $t->foreignId('tarea_id')->constrained('tareas_catalogo')->cascadeOnDelete();

      // Copias para búsquedas/tabla
      $t->enum('proceso', ['premontaje','montaje','pintura','chasis']);
      $t->string('seccion', 100)->nullable();
      $t->string('label', 255);

      // Estado/fechas
      $t->enum('estado', ['pendiente','en_progreso','finalizada'])->default('pendiente');
      $t->dateTime('fecha_inicio')->nullable();
      $t->dateTime('fecha_fin')->nullable();

      // Campos opcionales según área
      $t->string('color', 100)->nullable();
      $t->string('ral', 50)->nullable();
      $t->string('bastidor', 255)->nullable();

      // Piezas parciales (checkboxes)
      $t->json('piezas_check')->nullable();

      $t->timestamps();
      $t->index(['proceso','estado','fecha_inicio','fecha_fin']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('tareas_usuarios');
  }
};
