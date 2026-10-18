<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tutoria', function (Blueprint $table) {
            $table->string('materia_nombre')->nullable();
        });

        DB::table('solicitudes_tutoria')
            ->select(['id', 'materia_id'])
            ->orderBy('id')
            ->get()
            ->each(function ($request): void {
                $careerName = DB::table('carreras')
                    ->where('id', $request->materia_id)
                    ->value('nombre');

                DB::table('solicitudes_tutoria')
                    ->where('id', $request->id)
                    ->update(['materia_nombre' => $careerName]);
            });

        Schema::table('solicitudes_tutoria', function (Blueprint $table) {
            $table->dropForeign(['materia_id']);
            $table->unsignedBigInteger('materia_id')->nullable()->change();
        });

        DB::table('solicitudes_tutoria')->update(['materia_id' => null]);

        Schema::table('solicitudes_tutoria', function (Blueprint $table) {
            $table->foreign('materia_id')
                ->references('id')
                ->on('materias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tutoria', function (Blueprint $table) {
            $table->dropForeign(['materia_id']);
        });

        DB::table('solicitudes_tutoria')
            ->select(['id', 'materia_id', 'materia_nombre'])
            ->orderBy('id')
            ->get()
            ->each(function ($request): void {
                $careerId = $request->materia_id
                    ? DB::table('materias')
                        ->join('semestres', 'materias.semestre_id', '=', 'semestres.id')
                        ->where('materias.id', $request->materia_id)
                        ->value('semestres.carrera_id')
                    : DB::table('carreras')
                        ->where('nombre', $request->materia_nombre)
                        ->value('id');

                DB::table('solicitudes_tutoria')
                    ->where('id', $request->id)
                    ->update(['materia_id' => $careerId]);
            });

        Schema::table('solicitudes_tutoria', function (Blueprint $table) {
            $table->dropColumn('materia_nombre');
            $table->unsignedBigInteger('materia_id')->nullable(false)->change();
            $table->foreign('materia_id')
                ->references('id')
                ->on('carreras')
                ->restrictOnDelete();
        });
    }
};
