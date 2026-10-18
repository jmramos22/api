<?php

namespace App\Http\Controllers;

use App\Models\TutorApplication;
use App\Models\Carrera;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TutorApplicationController extends Controller
{
    public function show(Request $request)
    {
        $this->requireRole($request, 'ESTUDIANTE');

        return response()->json($request->user()
            ->tutorApplication()
            ->with('materias:id,nombre,semestre_id')
            ->first(['id', 'status', 'temas_dominados', 'created_at', 'reviewed_at']));
    }

    public function store(Request $request)
    {
        $this->requireRole($request, 'ESTUDIANTE');

        $validated = $request->validate([
            'kardex' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'materias' => ['required', 'array', 'min:1'],
            'materias.*' => ['required', 'integer', 'distinct', 'exists:materias,id'],
            'temas_dominados' => ['required', 'string', 'max:1000'],
        ]);

        $previousKardex = null;
        $kardexPath = null;
        $saved = false;

        try {
            DB::transaction(function () use ($request, $validated, &$previousKardex, &$kardexPath): void {
                $student = User::query()
                    ->lockForUpdate()
                    ->findOrFail($request->user()->id_usuario);
                $application = $student->tutorApplication()->first();

                if ($application?->status === 'PENDIENTE') {
                    throw ValidationException::withMessages([
                        'kardex' => 'Tu postulación ya está pendiente de revisión.',
                    ]);
                }
                if ($application?->status === 'APROBADA' || $student->rol === 'TUTOR') {
                    throw ValidationException::withMessages([
                        'kardex' => 'Tu cuenta ya está registrada como tutor.',
                    ]);
                }

                $career = Carrera::query()
                    ->where('nombre', $student->carrera)
                    ->where('activa', true)
                    ->firstOrFail();
                $eligibleSubjects = Materia::query()
                    ->whereIn('id', $validated['materias'])
                    ->whereHas('semestre', fn ($query) => $query->where('carrera_id', $career->id))
                    ->count();

                if ($eligibleSubjects !== count($validated['materias'])) {
                    throw ValidationException::withMessages([
                        'materias' => 'Selecciona únicamente materias del plan de estudios de tu carrera.',
                    ]);
                }

                $previousKardex = $application?->kardex_path;
                $kardexPath = Storage::disk('local')->putFile('tutor-applications', $validated['kardex']);

                if (! $kardexPath) {
                    throw new \RuntimeException('No se pudo guardar el kardex académico.');
                }

                $application = $student->tutorApplication()->updateOrCreate(
                    ['student_id' => $student->id_usuario],
                    [
                        'kardex_path' => $kardexPath,
                        'temas_dominados' => $validated['temas_dominados'],
                        'status' => 'PENDIENTE',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ],
                );
                $application->materias()->sync($validated['materias']);
            });
            $saved = true;
        } finally {
            if (! $saved && $kardexPath) {
                Storage::disk('local')->delete($kardexPath);
            }
        }

        if ($previousKardex) {
            Storage::disk('local')->delete($previousKardex);
        }

        return response()->json([
            'message' => 'Tu postulación se envió al administrador para su revisión.',
            'status' => 'PENDIENTE',
        ], 201);
    }

    public function index(Request $request)
    {
        $this->requireRole($request, 'ADMIN');

        return response()->json(
            TutorApplication::query()
                ->with([
                    'student:id_usuario,nombre,apellido,correo_institucional,carrera',
                    'materias:id,nombre,semestre_id',
                    'materias.semestre:id,numero',
                ])
                ->where('status', 'PENDIENTE')
                ->oldest()
                ->get(['id', 'student_id', 'status', 'temas_dominados', 'created_at']),
        );
    }

    public function downloadKardex(Request $request, TutorApplication $application)
    {
        $this->requireRole($request, 'ADMIN');

        abort_unless(
            Storage::disk('local')->exists($application->kardex_path),
            404,
            'El archivo de kardex no está disponible.',
        );

        return Storage::disk('local')->download(
            $application->kardex_path,
            "kardex-postulacion-{$application->student_id}.".pathinfo($application->kardex_path, PATHINFO_EXTENSION),
        );
    }

    public function decide(Request $request, TutorApplication $application)
    {
        $this->requireRole($request, 'ADMIN');

        $validated = $request->validate([
            'decision' => ['required', 'in:APROBADA,RECHAZADA'],
        ]);

        DB::transaction(function () use ($request, $application, $validated): void {
            $application = TutorApplication::query()
                ->lockForUpdate()
                ->with('student')
                ->findOrFail($application->id);

            abort_unless($application->status === 'PENDIENTE', 422, 'La postulación ya fue revisada.');
            abort_unless($application->student->rol === 'ESTUDIANTE', 422, 'El usuario ya no es estudiante.');

            $application->update([
                'status' => $validated['decision'],
                'reviewed_by' => $request->user()->id_usuario,
                'reviewed_at' => now(),
            ]);

            if ($validated['decision'] === 'APROBADA') {
                $application->student->update(['rol' => 'TUTOR']);
            }
        });

        return response()->json([
            'message' => $validated['decision'] === 'APROBADA'
                ? 'La postulación fue aprobada y el usuario ahora es tutor.'
                : 'La postulación fue rechazada; el usuario conserva su cuenta de estudiante.',
        ]);
    }

    private function requireRole(Request $request, string $role): void
    {
        abort_unless($request->user()->rol === $role, 403, 'No tienes permiso para realizar esta acción.');
    }
}
