<?php

namespace App\Http\Controllers;

use App\Models\DisponibilidadTutor;
use App\Models\SolicitudTutoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisponibilidadTutorController extends Controller
{
    private const ESTADOS_QUE_RESERVAN = ['PENDIENTE', 'ACEPTADA'];

    public function index(Request $request)
    {
        $this->requireRole($request, 'TUTOR');

        $slots = DisponibilidadTutor::query()
            ->where('tutor_id', $request->user()->id_usuario)
            ->where('inicio', '>', now())
            ->withCount([
                'solicitudes as reservas_activas_count' => fn ($query) => $query
                    ->whereIn('estado', self::ESTADOS_QUE_RESERVAN),
            ])
            ->orderBy('inicio')
            ->get()
            ->map(fn (DisponibilidadTutor $slot) => [
                'id' => $slot->id,
                'inicio' => $slot->inicio,
                'fin' => $slot->fin,
                'libre' => $slot->reservas_activas_count === 0,
            ]);

        return response()->json([
            'horarios_libres' => $slots->where('libre', true)->count(),
            'disponibilidades' => $slots->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, 'TUTOR');

        $validated = $request->validate([
            'inicio' => ['required', 'date', 'after:now'],
            'fin' => ['required', 'date', 'after:inicio'],
        ]);
        $start = Carbon::parse($validated['inicio']);
        $end = Carbon::parse($validated['fin']);

        $slot = DB::transaction(function () use ($request, $start, $end): DisponibilidadTutor {
            $tutor = User::query()
                ->lockForUpdate()
                ->findOrFail($request->user()->id_usuario);

            $overlapsExistingSlot = DisponibilidadTutor::query()
                ->where('tutor_id', $tutor->id_usuario)
                ->where('inicio', '<', $end)
                ->where('fin', '>', $start)
                ->exists();

            if ($overlapsExistingSlot) {
                throw ValidationException::withMessages([
                    'inicio' => 'El horario se cruza con otro bloque que ya publicaste.',
                ]);
            }

            $overlapsExistingRequest = SolicitudTutoria::query()
                ->where('tutor_id', $tutor->id_usuario)
                ->whereIn('estado', self::ESTADOS_QUE_RESERVAN)
                ->where('fecha_hora', '>=', $start)
                ->where('fecha_hora', '<', $end)
                ->exists();

            if ($overlapsExistingRequest) {
                throw ValidationException::withMessages([
                    'inicio' => 'El horario se cruza con una solicitud o sesión ya reservada.',
                ]);
            }

            return DisponibilidadTutor::query()->create([
                'tutor_id' => $tutor->id_usuario,
                'inicio' => $start,
                'fin' => $end,
            ]);
        });

        return response()->json([
            'message' => 'Tu disponibilidad se publicó correctamente.',
            'disponibilidad' => $slot,
        ], 201);
    }

    public function availableForStudent(Request $request, User $tutor)
    {
        $this->requireRole($request, 'ESTUDIANTE');
        $student = $request->user();

        abort_unless(
            $tutor->rol === 'TUTOR' && $tutor->carrera === $student->carrera,
            404,
            'No se encontró un tutor disponible para tu carrera.',
        );

        $slots = DisponibilidadTutor::query()
            ->where('tutor_id', $tutor->id_usuario)
            ->where('inicio', '>', now())
            ->whereDoesntHave('solicitudes', fn ($query) => $query
                ->whereIn('estado', self::ESTADOS_QUE_RESERVAN))
            ->orderBy('inicio')
            ->get(['id', 'inicio', 'fin']);

        return response()->json([
            'disponibilidades' => $slots,
        ]);
    }

    public function destroy(Request $request, DisponibilidadTutor $disponibilidad)
    {
        $this->requireRole($request, 'TUTOR');

        abort_unless(
            $disponibilidad->tutor_id === $request->user()->id_usuario,
            404,
            'No se encontró ese horario disponible.',
        );

        $disponibilidad = DB::transaction(function () use ($disponibilidad): DisponibilidadTutor {
            $slot = DisponibilidadTutor::query()
                ->lockForUpdate()
                ->findOrFail($disponibilidad->id);

            if ($slot->solicitudes()->whereIn('estado', self::ESTADOS_QUE_RESERVAN)->exists()) {
                abort(409, 'No puedes eliminar un horario que tiene una solicitud pendiente o una sesión aceptada.');
            }

            $slot->delete();

            return $slot;
        });

        return response()->json([
            'message' => 'El horario se eliminó correctamente.',
        ]);
    }

    private function requireRole(Request $request, string $role): void
    {
        if ($request->user()->rol !== $role) {
            abort(403, 'No tienes permiso para gestionar esta disponibilidad.');
        }
    }
}
