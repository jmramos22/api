<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /**
     * Registra una cuenta con los datos validados y entrega un token de acceso.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'correo_institucional' => $request->correo_institucional,
            'password' => Hash::make($request->password),
            'carrera' => $request->carrera,
            'rol' => 'ESTUDIANTE',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado exitosamente.',
            'token' => $token,
            'user' => $user,
        ], 201);
    }
}