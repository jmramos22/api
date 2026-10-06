<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Permite que la solicitud de registro llegue a la validación.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define los requisitos para los datos enviados al crear una cuenta.
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'correo_institucional' => [
                'required',
                'email',
                'unique:users,correo_institucional',
                'regex:/^[a-zA-Z0-9._%+-]+@uas\.edu\.mx$/i'
            ],
            'password' => 'required|string|min:8',
            'carrera' => ['required', 'string', 'exists:carreras,nombre'],
        ];
    }
}