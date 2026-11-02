<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidades_tutor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('users', 'id_usuario')->cascadeOnDelete();
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->timestamps();

            $table->index(['tutor_id', 'inicio']);
        });

        Schema::table('solicitudes_tutoria', function (Blueprint $table) {
            $table->foreignId('disponibilidad_id')
                ->nullable()
                ->constrained('disponibilidades_tutor')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tutoria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disponibilidad_id');
        });

        Schema::dropIfExists('disponibilidades_tutor');
    }
};
