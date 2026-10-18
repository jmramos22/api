<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudTutoria extends Model
{
    public const ESTADOS = [
        'PENDIENTE',
        'ACEPTADA',
        'RECHAZADA',
        'CANCELADA',
        'COMPLETADA',
    ];

    protected $table = 'solicitudes_tutoria';

    protected $fillable = [
        'estudiante_id',
        'tutor_id',
        'materia_id',
        'fecha_hora',
        'mensaje',
        'estado',
        'calificacion',
        'comentario_calificacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'calificacion' => 'integer',
        ];
    }

    /** Obtiene al estudiante que envió la solicitud. */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id', 'id_usuario');
    }

    /** Obtiene al tutor elegido para la solicitud. */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id', 'id_usuario');
    }

    /** Obtiene la materia seleccionada en el catálogo de carreras. */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }
}
