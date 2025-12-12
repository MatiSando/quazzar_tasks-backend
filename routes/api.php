<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\TareasController;
use App\Http\Controllers\Api\TareaCatalogoController;

// Ping
Route::get('/ping', fn () => response()->json(['ok' => true]));

// ===== LOGIN =====
Route::post('/login', [AuthController::class, 'login']);

// ===== USUARIOS (omito CRUD si ya lo tienes en otro archivo) =====
Route::get('/usuarios', [UsuarioController::class, 'index']);
Route::post('/usuarios', [UsuarioController::class, 'store']);
Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
Route::post('/usuarios/{id}/reset-password', [UsuarioController::class, 'resetPassword']);
Route::post('/usuarios/{id}/change-password', [UsuarioController::class, 'changePassword']);

// ===== TAREAS (lectura dinámica de columnas por área) =====
Route::get('/tareas', [TareaCatalogoController::class, 'index']);
Route::post('/tareas', [TareaCatalogoController::class, 'store']);
Route::put('/tareas/{id}', [TareaCatalogoController::class, 'update']);
Route::delete('/tareas/{id}', [TareaCatalogoController::class, 'destroy']);

Route::post('/tareas/iniciar', [TareasController::class, 'iniciar']);
Route::put('/tareas/{area}/{id}', [TareasController::class, 'actualizar']);
Route::post('/tareas/{area}/{id}/finalizar', [TareasController::class, 'finalizar']);
Route::post('/tareas/{area}/{id}/pendiente', [TareasController::class, 'pendiente']);

// ⬅️ Pendiente por VIN (con ?user_id=) — SOLO devuelve si es del usuario
Route::get('/tareas/{area}/pendiente/{vin}', [TareasController::class, 'pendientePorVin']);

// Snapshots / pendientes del usuario
Route::get('/pendientes/{usuarioId}', [TareasController::class, 'pendientesUsuario']);
Route::get('/tareas/{area}/{id}/snapshot', [TareasController::class, 'snapshotPendiente']);

// Estado VIN en chasis
Route::get('/chasis/vin-estado/{vin}', [TareasController::class, 'vinEstadoChasis']);

// VINs y colores
Route::get('/chasis/bastidores', [TareasController::class, 'bastidoresChasis']);
Route::get('/pintura/colores', [TareasController::class, 'coloresPintura']);
Route::get('/pintura/color-por-vin/{vin}', [TareasController::class, 'colorPinturaPorVin']);

// Disponibilidad de VIN en montaje (evitar duplicados finalizados)
Route::get('/montaje/vin-disponible/{vin}', [TareasController::class, 'vinDisponibleMontaje']);

// Buscador tabla tareas (logs)
Route::get('/busquedas/tareas', [TareasController::class, 'tareasLog']);

// Opcional: saber si un VIN está finalizado en un área
Route::get('/tareas/{area}/finalizado/{vin}', [TareasController::class, 'finalizadoPorVin']);
