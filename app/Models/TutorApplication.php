<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TutorApplication extends Model
{
    protected $fillable = [
        'student_id',
        'kardex_path',
        'status',
        'temas_dominados',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id', 'id_usuario');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id_usuario');
    }

    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(
            Materia::class,
            'tutor_application_materias',
            'tutor_application_id',
            'materia_id',
        )->withTimestamps();
    }
}
