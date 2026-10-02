<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Models\Carrera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

// Ruta para obtener las carreras activas (la usa el formulario de registro)
Route::get('/carreras', function () {
    return Carrera::where('activa', true)->orderBy('nombre')->get();
});

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [LoginController::class, 'logout']);
});