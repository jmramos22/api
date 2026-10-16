<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\AdminController;

class AdminController extends Controller
{
    /**
     * Verifica que el usuario autenticado sea Administrador.
     */
    private function checkAdmin(Request $request)
    {
        if ($request->user()->rol !== 'ADMIN') {
            abort(403, 'Acceso denegado. Se requieren permisos de Administrador.');
        }
    }

    /**
     * Obtiene TODAS las carreras (activas e inactivas) para el panel de control.
     */
    public function listCarreras(Request $request)
    {
        $this->checkAdmin($request);
        return response()->json(Carrera::orderBy('nombre')->get());
    }

    /**
     * Crea una nueva carrera.
     */
    public function storeCarrera(Request $request)
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:carreras,nombre'],
            'activa' => ['boolean'],
        ]);

        $carrera = Carrera::create([
            'nombre' => $validated['nombre'],
            'activa' => $validated['activa'] ?? true, // Por defecto se crean activas
        ]);

        return response()->json([
            'message' => 'Carrera creada exitosamente.', 
            'carrera' => $carrera
        ], 201);
    }

    /**
     * Actualiza una carrera existente (nombre o cambiarla a inactiva).
     */
    public function updateCarrera(Request $request, $id)
    {
        $this->checkAdmin($request);

        $carrera = Carrera::findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255', 'unique:carreras,nombre,' . $carrera->id],
            'activa' => ['boolean'],
        ]);

        $carrera->update($validated);

        return response()->json([
            'message' => 'Carrera actualizada correctamente.', 
            'carrera' => $carrera
        ]);
    }

    /**
     * Obtiene la lista de usuarios para que el Admin pueda promoverlos.
     */
    public function listUsers(Request $request)
    {
        $this->checkAdmin($request);
        // Traemos a los usuarios ordenados por fecha de registro
        return response()->json(User::orderBy('created_at', 'desc')->get());
    }

    /**
     * Cambia el rol de un usuario (ej. de ESTUDIANTE a TUTOR).
     */
    public function updateUserRole(Request $request, $idUsuario)
    {
        $this->checkAdmin($request);

        // Usamos id_usuario porque así está en tu base de datos de PostgreSQL
        $user = User::where('id_usuario', $idUsuario)->firstOrFail();

        $validated = $request->validate([
            'rol' => ['required', 'string', 'in:ESTUDIANTE,TUTOR,ADMIN'],
        ]);

        $user->update([
            'rol' => $validated['rol']
        ]);

        return response()->json([
            'message' => "El rol del usuario ha sido cambiado a {$validated['rol']}.", 
            'user' => $user
        ]);
    }
}