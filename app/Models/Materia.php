<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Materia extends Model
{
    protected $fillable = ['semestre_id', 'nombre', 'activa'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class);
    }
}
