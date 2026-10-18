<?php

use Database\Seeders\CurriculumSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_id')->constrained('carreras')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->timestamps();

            $table->unique(['carrera_id', 'numero']);
        });

        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
            $table->string('nombre');
            $table->timestamps();

            $table->unique(['semestre_id', 'nombre']);
        });

        (new CurriculumSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('materias');
        Schema::dropIfExists('semestres');
    }
};
