<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('users', 'id_usuario')->cascadeOnDelete();
            $table->string('kardex_path');
            $table->string('status', 20)->default('PENDIENTE');
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id_usuario')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_applications');
    }
};
