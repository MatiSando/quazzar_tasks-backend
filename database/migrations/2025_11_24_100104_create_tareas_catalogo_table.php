<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tareas_catalogo', function (Blueprint $t) {
      $t->id();
      $t->enum('proceso', ['premontaje','montaje','pintura','chasis']);
      $t->string('seccion', 100)->nullable();
      $t->string('label', 255);
      $t->boolean('activa')->default(true);
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::dropIfExists('tareas_catalogo');
  }
};
