<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutor_applications', function (Blueprint $table) {
            $table->text('temas_dominados')->nullable();
        });

        Schema::create('tutor_application_materias', function (Blueprint $table) {
            $table->foreignId('tutor_application_id')
                ->constrained('tutor_applications')
                ->cascadeOnDelete();
            $table->foreignId('materia_id')
                ->constrained('materias')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['tutor_application_id', 'materia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_application_materias');

        Schema::table('tutor_applications', function (Blueprint $table) {
            $table->dropColumn('temas_dominados');
        });
    }
};
