<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisponibilidadTutor extends Model
{
    protected $table = 'disponibilidades_tutor';

    protected $fillable = [
        'tutor_id',
        'inicio',
        'fin',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id', 'id_usuario');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTutoria::class, 'disponibilidad_id');
    }
}
