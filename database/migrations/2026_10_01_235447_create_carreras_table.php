<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de carreras disponibles para asociar a las cuentas.
     */
    public function up(): void
    {
        Schema::create('carreras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activa')->default(true); // Permite ocultar carreras sin borrarlas
            $table->timestamps();
        });
    }

    /**
     * Elimina la tabla de carreras al revertir esta migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('carreras');
    }
};
