<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda las solicitudes de tutoría y sus participantes.
     */
    public function up(): void
    {
        Schema::create('solicitudes_tutoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users', 'id_usuario')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('users', 'id_usuario')->cascadeOnDelete();
            $table->foreignId('materia_id')->constrained('carreras')->restrictOnDelete();
            $table->dateTime('fecha_hora');
            $table->text('mensaje')->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->timestamps();

            $table->index(['estudiante_id', 'estado']);
            $table->index(['tutor_id', 'estado']);
        });
    }

    /**
     * Elimina las solicitudes al revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_tutoria');
    }
};
