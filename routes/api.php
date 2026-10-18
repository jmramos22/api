<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\ProfileImageController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SolicitudTutoriaController;
use App\Http\Controllers\TutorApplicationController;
use App\Models\Carrera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

// Ruta para obtener las carreras activas (la usa el formulario de registro)
Route::get('/carreras', function () {
    // Devuelve solo carreras disponibles y en orden alfabético.
    return Carrera::where('activa', true)->orderBy('nombre')->get();
});

Route::get('/user/image/{userId}', [ProfileImageController::class, 'show'])
    ->middleware('signed:relative')
    ->name('api.user.image.show');

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request, ProfileImageController $images) {
        // Obtiene el usuario autenticado mediante el token de Sanctum.
        $user = $request->user();
        $data = $user->toArray();
        $data['image_url'] = $images->urlFor($request, $user->getKey());

        return response()->json($data);
    });
    
   // Rutas para actualizar la imagen de perfil
    Route::post('/user/image', [ProfileImageController::class, 'store']);
    Route::delete('/user/image', [ProfileImageController::class, 'destroy']);

    // Ruta para actualizar la contraseña del usuario autenticado
    Route::put('/user/password', [UpdatePasswordController::class, 'update']);
    
    // Ruta para cerrar sesión (revocar token)
    Route::post('/logout', [LoginController::class, 'logout']);
    
    //Rutas para la gestión de solicitudes de tutoría y aplicaciones de tutoría
    Route::get('/tutoring/application', [TutorApplicationController::class, 'show']);
    Route::post('/tutoring/application', [TutorApplicationController::class, 'store']);
    
    // Rutas para la gestión de solicitudes de tutoría por parte del administrador
    Route::get('/admin/tutor-applications', [TutorApplicationController::class, 'index']);
    Route::get('/admin/tutor-applications/{application}/kardex', [TutorApplicationController::class, 'downloadKardex']);
    Route::patch('/admin/tutor-applications/{application}', [TutorApplicationController::class, 'decide']);
    
    // Rutas para la gestión de solicitudes de tutoría
    Route::get('/tutoring/options', [SolicitudTutoriaController::class, 'opciones']);
    Route::get('/tutoring/profile', [SolicitudTutoriaController::class, 'perfilTutor']);
    Route::get('/tutoring/requests', [SolicitudTutoriaController::class, 'index']);
    Route::post('/tutoring/requests', [SolicitudTutoriaController::class, 'store']);
    Route::patch('/tutoring/requests/{solicitud}/status', [SolicitudTutoriaController::class, 'updateEstado']);
    Route::post('/tutoring/requests/{solicitud}/rating', [SolicitudTutoriaController::class, 'calificar']);


    // ==========================================
    // RUTAS DE ADMINISTRADOR (HU-05)
    // ==========================================
    Route::get('/admin/carreras', [AdminController::class, 'listCarreras']);
    Route::post('/admin/carreras', [AdminController::class, 'storeCarrera']);
    Route::put('/admin/carreras/{id}', [AdminController::class, 'updateCarrera']);
    
    Route::get('/admin/users', [AdminController::class, 'listUsers']);
    Route::put('/admin/users/{idUsuario}/rol', [AdminController::class, 'updateUserRole']);
    Route::patch('/admin/users/{idUsuario}/status', [AdminController::class, 'updateUserStatus']);

});
