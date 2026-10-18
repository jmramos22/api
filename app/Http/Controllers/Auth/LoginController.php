<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Procesa el inicio de sesión vía API.
     * Devuelve un token de Sanctum en JSON.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'correo_institucional' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $email = strtolower(trim((string) $credentials['correo_institucional']));
        $user = User::whereRaw('LOWER(correo_institucional) = ?', [$email])->first();

        if (! $user || ! $user->is_active || ! Hash::check((string) $credentials['password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
                'errors' => [
                    'correo_institucional' => ['Las credenciales proporcionadas no coinciden con nuestros registros.'],
                ],
            ], 422);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Cierra la sesión eliminando el token actual.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}