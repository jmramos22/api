<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    protected $fillable = ['nombre', 'activa'];

    public function semestres(): HasMany
    {
        return $this->hasMany(Semestre::class)->orderBy('numero');
    }
}
