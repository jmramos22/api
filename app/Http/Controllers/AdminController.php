<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\Materia;
use App\Models\Semestre;
use App\Models\SolicitudTutoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        return response()->json(
            Carrera::with(['semestres.materias'])->orderBy('nombre')->get(),
        );
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
            'semestres' => ['sometimes', 'array'],
            'semestres.*.numero' => ['required', 'integer', 'min:1', 'max:20', 'distinct'],
            'semestres.*.materias' => ['required', 'array', 'min:1'],
            'semestres.*.materias.*' => ['required', 'string', 'max:255', 'regex:/\S/', 'distinct'],
        ]);

        $carrera = DB::transaction(function () use ($validated): Carrera {
            $carrera = Carrera::create([
                'nombre' => $validated['nombre'],
                'activa' => $validated['activa'] ?? true,
            ]);

            if (array_key_exists('semestres', $validated)) {
                $this->syncStudyPlan($carrera, $validated['semestres']);
            }

            return $carrera->load('semestres.materias');
        });

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
            'semestres' => ['sometimes', 'array'],
            'semestres.*.numero' => ['required', 'integer', 'min:1', 'max:20', 'distinct'],
            'semestres.*.materias' => ['required', 'array', 'min:1'],
            'semestres.*.materias.*' => ['required', 'string', 'max:255', 'regex:/\S/', 'distinct'],
        ]);

        $carrera = DB::transaction(function () use ($carrera, $validated): Carrera {
            $attributes = collect($validated)->except('semestres')->all();
            $previousName = $carrera->nombre;
            $carrera->update($attributes);

            if (isset($attributes['nombre']) && $attributes['nombre'] !== $previousName) {
                User::query()->where('carrera', $previousName)->update(['carrera' => $attributes['nombre']]);
            }

            if (array_key_exists('semestres', $validated)) {
                $this->syncStudyPlan($carrera, $validated['semestres']);
            }

            return $carrera->fresh()->load('semestres.materias');
        });

        return response()->json([
            'message' => 'Carrera actualizada correctamente.', 
            'carrera' => $carrera
        ]);
    }

    private function syncStudyPlan(Carrera $carrera, array $semesters): void
    {
        $semesterNumbers = collect($semesters)->pluck('numero')->map(fn ($number) => (int) $number)->all();

        $carrera->semestres()
            ->whereNotIn('numero', $semesterNumbers)
            ->get()
            ->each(function (Semestre $semester): void {
                $this->deleteSemester($semester);
            });

        foreach ($semesters as $semesterData) {
            $semester = Semestre::query()->updateOrCreate(
                [
                    'carrera_id' => $carrera->id,
                    'numero' => $semesterData['numero'],
                ],
                [],
            );
            $subjectNames = collect($semesterData['materias'])
                ->map(fn (string $name) => trim($name))
                ->filter()
                ->unique()
                ->values();

            $semester->materias()
                ->whereNotIn('nombre', $subjectNames->all())
                ->get()
                ->each(function (Materia $subject): void {
                    SolicitudTutoria::query()
                        ->where('materia_id', $subject->id)
                        ->whereNull('materia_nombre')
                        ->update(['materia_nombre' => $subject->nombre]);
                    $subject->delete();
                });

            foreach ($subjectNames as $subjectName) {
                Materia::query()->firstOrCreate([
                    'semestre_id' => $semester->id,
                    'nombre' => $subjectName,
                ]);
            }
        }
    }

    private function deleteSemester(Semestre $semester): void
    {
        $semester->materias->each(function (Materia $subject): void {
            SolicitudTutoria::query()
                ->where('materia_id', $subject->id)
                ->whereNull('materia_nombre')
                ->update(['materia_nombre' => $subject->nombre]);
        });
        $semester->delete();
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
            'rol' => ['required', Rule::in(['ESTUDIANTE', 'ADMIN'])],
        ]);

        $user->update([
            'rol' => $validated['rol']
        ]);

        return response()->json([
            'message' => "El rol del usuario ha sido cambiado a {$validated['rol']}.", 
            'user' => $user
        ]);
    }

    public function updateUserStatus(Request $request, $idUsuario)
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $active = (bool) $validated['is_active'];
        $admin = $request->user();
        $user = DB::transaction(function () use ($admin, $idUsuario, $active): User {
            $activeAdmins = User::query()
                ->where('rol', 'ADMIN')
                ->where('is_active', true)
                ->orderBy('id_usuario')
                ->lockForUpdate()
                ->get(['id_usuario']);
            $user = User::query()->lockForUpdate()->findOrFail($idUsuario);

            if (! $active && (int) $admin->id_usuario === (int) $user->id_usuario) {
                throw ValidationException::withMessages([
                    'is_active' => 'No puedes dar de baja tu propia cuenta de administrador.',
                ]);
            }

            if (! $active && $user->is_active && $user->rol === 'ADMIN' && $activeAdmins->count() <= 1) {
                throw ValidationException::withMessages([
                    'is_active' => 'No puedes dar de baja al último administrador activo.',
                ]);
            }

            $user->update(['is_active' => $active]);

            if (! $active) {
                $user->tokens()->delete();
            }

            return $user->fresh();
        });

        return response()->json([
            'message' => $active
                ? 'La cuenta fue reactivada correctamente.'
                : 'La cuenta fue dada de baja y sus sesiones activas fueron cerradas.',
            'user' => $user,
        ]);
    }
}