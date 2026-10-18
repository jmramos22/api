<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\Materia;
use App\Models\SolicitudTutoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SolicitudTutoriaController extends Controller
{
    /**
     * Devuelve las materias y los tutores disponibles para el formulario del estudiante.
     */
    public function opciones(Request $request)
    {
        $this->requireRole($request, 'ESTUDIANTE');

        $student = $request->user();
        $career = Carrera::query()
            ->where('nombre', $student->carrera)
            ->where('activa', true)
            ->first();

        if (! $career) {
            return response()->json([
                'message' => 'La carrera asociada a tu perfil no está disponible. Actualiza tu perfil o contacta al administrador.',
            ], 422);
        }

        $materias = Materia::query()
            ->with('semestre:id,carrera_id,numero')
            ->whereHas('semestre', fn ($query) => $query->where('carrera_id', $career->id))
            ->orderBy('nombre')
            ->get(['id', 'semestre_id', 'nombre']);

        return response()->json([
            'materias' => $materias->map(fn (Materia $materia) => [
                'id' => $materia->id,
                'nombre' => $materia->nombre,
                'semestre' => $materia->semestre->numero,
                'carrera' => $career->nombre,
            ]),
            'tutores' => User::query()
                ->where('rol', 'TUTOR')
                ->where('carrera', $career->nombre)
                ->orderBy('nombre')
                ->get(['id_usuario', 'nombre', 'apellido'])
                ->map(fn (User $tutor) => [
                    'id_usuario' => $tutor->id_usuario,
                    'nombre' => $tutor->nombre,
                    'apellido' => $tutor->apellido,
                ]),
        ]);
    }

    /**
     * Devuelve el plan de estudios de la carrera asignada al tutor.
     */
    public function perfilTutor(Request $request)
    {
        $this->requireRole($request, 'TUTOR');

        $career = Carrera::query()
            ->where('nombre', $request->user()->carrera)
            ->firstOrFail();
        $application = $request->user()
            ->tutorApplication()
            ->with('materias.semestre:id,numero')
            ->first();

        return response()->json([
            'carrera' => [
                'id' => $career->id,
                'nombre' => $career->nombre,
            ],
            'materias_ofrecidas' => $application?->materias
                ->map(fn (Materia $materia) => [
                    'id' => $materia->id,
                    'nombre' => $materia->nombre,
                    'semestre' => $materia->semestre->numero,
                ])
                ->values() ?? [],
            'temas_dominados' => $application?->temas_dominados,
            'semestres' => $career->semestres()
                ->with('materias:id,semestre_id,nombre')
                ->get(['id', 'carrera_id', 'numero'])
                ->map(fn ($semester) => [
                    'id' => $semester->id,
                    'numero' => $semester->numero,
                    'materias' => $semester->materias->map(fn (Materia $materia) => [
                        'id' => $materia->id,
                        'nombre' => $materia->nombre,
                    ]),
                ]),
        ]);
    }

    /**
     * Lista solicitudes según la responsabilidad del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = SolicitudTutoria::query()
            ->with([
                'estudiante:id_usuario,nombre,apellido',
                'tutor:id_usuario,nombre,apellido',
                'materia:id,nombre',
            ])
            ->latest();

        if ($user->rol === 'ESTUDIANTE') {
            $query->where('estudiante_id', $user->id_usuario);
        } elseif ($user->rol === 'TUTOR') {
            $query->where('tutor_id', $user->id_usuario);
        } elseif ($user->rol !== 'ADMIN') {
            abort(403, 'No tienes permiso para consultar solicitudes de tutoría.');
        }

        return response()->json($query->get());
    }

    /**
     * Registra una solicitud nueva vinculada al estudiante autenticado.
     */
    public function store(Request $request)
    {
        $this->requireRole($request, 'ESTUDIANTE');

        $validated = $request->validate([
            'tutor_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id_usuario')->where('rol', 'TUTOR'),
            ],
            'materia_id' => [
                'required',
                'integer',
                Rule::exists('materias', 'id'),
            ],
            'fecha_hora' => ['required', 'date', 'after:now'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
        ]);

        $career = Carrera::query()
            ->where('nombre', $request->user()->carrera)
            ->where('activa', true)
            ->firstOrFail();
        $materiaIsAvailable = Materia::query()
            ->whereKey($validated['materia_id'])
            ->whereHas('semestre', fn ($query) => $query->where('carrera_id', $career->id))
            ->exists();
        if (! $materiaIsAvailable) {
            throw ValidationException::withMessages([
                'materia_id' => 'Solo puedes solicitar materias de la carrera asociada a tu cuenta.',
            ]);
        }

        $tutorIsRegisteredInCareer = User::query()
            ->where('id_usuario', $validated['tutor_id'])
            ->where('rol', 'TUTOR')
            ->where('carrera', $career->nombre)
            ->exists();
        if (! $tutorIsRegisteredInCareer) {
            throw ValidationException::withMessages([
                'tutor_id' => 'El tutor seleccionado no está registrado en tu carrera.',
            ]);
        }

        $solicitud = SolicitudTutoria::create([
            ...$validated,
            'estudiante_id' => $request->user()->id_usuario,
            'estado' => 'PENDIENTE',
        ]);

        return response()->json([
            'message' => 'La solicitud de tutoría se envió correctamente.',
            'solicitud' => $solicitud->load([
                'estudiante:id_usuario,nombre,apellido',
                'tutor:id_usuario,nombre,apellido',
                'materia:id,nombre',
            ]),
        ], 201);
    }

    /**
     * Cambia el estado solo si el participante tiene permiso y la transición es válida.
     */
    public function updateEstado(Request $request, SolicitudTutoria $solicitud)
    {
        $validated = $request->validate([
            'estado' => ['required', Rule::in(SolicitudTutoria::ESTADOS)],
        ]);

        // Bloquea el registro durante la transición para evitar respuestas simultáneas inconsistentes.
        $solicitud = DB::transaction(function () use ($request, $solicitud, $validated) {
            $solicitud = SolicitudTutoria::query()
                ->lockForUpdate()
                ->findOrFail($solicitud->id);

            $user = $request->user();
            $isTutor = $user->rol === 'TUTOR' && $solicitud->tutor_id === $user->id_usuario;
            $isStudent = $user->rol === 'ESTUDIANTE' && $solicitud->estudiante_id === $user->id_usuario;

            abort_unless($isTutor || $isStudent, 403, 'No tienes permiso para actualizar esta solicitud.');

            $nextStatus = $validated['estado'];
            $canTutorAccept = $isTutor
                && $solicitud->estado === 'PENDIENTE'
                && $nextStatus === 'ACEPTADA'
                && $solicitud->fecha_hora->isFuture();
            $canTutorReject = $isTutor
                && $solicitud->estado === 'PENDIENTE'
                && $nextStatus === 'RECHAZADA';
            $canTutorComplete = $isTutor
                && $solicitud->estado === 'ACEPTADA'
                && $nextStatus === 'COMPLETADA';
            $canCancelPendingRequest = $solicitud->estado === 'PENDIENTE';
            $canCancelAcceptedSession = $solicitud->estado === 'ACEPTADA'
                && $solicitud->fecha_hora->isFuture();
            $canStudentCancel = $isStudent
                && $nextStatus === 'CANCELADA'
                && ($canCancelPendingRequest || $canCancelAcceptedSession);

            abort_unless(
                $canTutorAccept || $canTutorReject || $canTutorComplete || $canStudentCancel,
                422,
                'La solicitud no permite ese cambio de estado.'
            );

            $solicitud->update(['estado' => $nextStatus]);

            return $solicitud->load([
                'estudiante:id_usuario,nombre,apellido',
                'tutor:id_usuario,nombre,apellido',
                'materia:id,nombre',
            ]);
        });

        return response()->json([
            'message' => 'El estado de la solicitud se actualizó correctamente.',
            'solicitud' => $solicitud,
        ]);
    }

    /**
     * Guarda una única calificación del estudiante para una tutoría completada.
     */
    public function calificar(Request $request, SolicitudTutoria $solicitud)
    {
        $this->requireRole($request, 'ESTUDIANTE');

        $validated = $request->validate([
            'calificacion' => ['required', 'integer', 'between:1,5'],
            'comentario_calificacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $solicitud = DB::transaction(function () use ($request, $solicitud, $validated) {
            $solicitud = SolicitudTutoria::query()
                ->lockForUpdate()
                ->findOrFail($solicitud->id);

            abort_unless(
                $solicitud->estudiante_id === $request->user()->id_usuario,
                403,
                'No tienes permiso para calificar esta tutoría.'
            );
            abort_unless(
                $solicitud->estado === 'COMPLETADA',
                422,
                'Solo puedes calificar tutorías completadas.'
            );
            abort_if($solicitud->calificacion !== null, 422, 'Esta tutoría ya fue calificada.');

            $solicitud->update($validated);

            return $solicitud->load([
                'estudiante:id_usuario,nombre,apellido',
                'tutor:id_usuario,nombre,apellido',
                'materia:id,nombre',
            ]);
        });

        return response()->json([
            'message' => 'Tu calificación se guardó correctamente.',
            'solicitud' => $solicitud,
        ]);
    }

    /**
     * Restringe una operación al rol requerido.
     */
    private function requireRole(Request $request, string $role): void
    {
        abort_unless($request->user()->rol === $role, 403, 'No tienes permiso para realizar esta acción.');
    }
}
