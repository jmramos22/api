<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TutorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_only_see_and_request_subjects_and_tutors_from_their_career(): void
    {
        $informatics = Carrera::where('nombre', 'Licenciatura en Informática')->firstOrFail();
        $dataScience = Carrera::where('nombre', 'Licenciatura en Ingeniería en Ciencia de Datos')->firstOrFail();
        $student = $this->createUser('ESTUDIANTE', 'student@uas.edu.mx', $informatics->nombre);
        $informaticsTutor = $this->createUser('TUTOR', 'informatics-tutor@uas.edu.mx', $informatics->nombre);
        $dataScienceTutor = $this->createUser('TUTOR', 'data-tutor@uas.edu.mx', $dataScience->nombre);
        $informaticsTutorToken = $informaticsTutor->createToken('tutor')->plainTextToken;
        $informaticsSubject = Materia::whereHas('semestre', fn ($query) => $query->where('carrera_id', $informatics->id))->firstOrFail();
        $dataScienceSubject = Materia::whereHas('semestre', fn ($query) => $query->where('carrera_id', $dataScience->id))->firstOrFail();
        $token = $student->createToken('student')->plainTextToken;

        $this->withToken($token)->getJson('/api/tutoring/options')
            ->assertOk()
            ->assertJsonCount(42, 'materias')
            ->assertJsonCount(1, 'tutores')
            ->assertJsonPath('tutores.0.id_usuario', $informaticsTutor->id_usuario);

        $this->withFreshToken($informaticsTutorToken)->getJson('/api/tutoring/profile')
            ->assertOk()
            ->assertJsonCount(0, 'materias_ofrecidas')
            ->assertJsonPath('temas_dominados', null);

        $payload = [
            'tutor_id' => $informaticsTutor->id_usuario,
            'materia_id' => $informaticsSubject->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ];
        $this->withFreshToken($token)->postJson('/api/tutoring/requests', $payload)
            ->assertCreated()
            ->assertJsonPath('solicitud.materia.id', $informaticsSubject->id);

        $payload['materia_id'] = $dataScienceSubject->id;
        $this->withFreshToken($token)->postJson('/api/tutoring/requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('materia_id');

        $payload['materia_id'] = $informaticsSubject->id;
        $payload['tutor_id'] = $dataScienceTutor->id_usuario;
        $this->withFreshToken($token)->postJson('/api/tutoring/requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tutor_id');
    }

    public function test_admin_reviews_private_kardex_and_only_approval_promotes_the_student(): void
    {
        Storage::fake('local');
        $informatics = Carrera::where('nombre', 'Licenciatura en Informática')->firstOrFail();
        $student = $this->createUser('ESTUDIANTE', 'student@uas.edu.mx', $informatics->nombre);
        $admin = $this->createUser('ADMIN', 'admin@uas.edu.mx');
        $subject = Materia::whereHas('semestre', fn ($query) => $query->where('carrera_id', $informatics->id))->firstOrFail();
        $studentToken = $student->createToken('student')->plainTextToken;
        $adminToken = $admin->createToken('admin')->plainTextToken;

        $this->withToken($studentToken)->post('/api/tutoring/application', [
            'kardex' => UploadedFile::fake()->create('kardex.pdf', 50, 'application/pdf'),
            'materias' => [$subject->id],
            'temas_dominados' => 'Álgebra y programación orientada a objetos.',
        ])->assertCreated()
            ->assertJsonPath('status', 'PENDIENTE');

        $applicationId = $student->tutorApplication()->value('id');
        Storage::disk('local')->assertExists($student->tutorApplication()->value('kardex_path'));
        $this->withFreshToken($studentToken)
            ->get("/api/admin/tutor-applications/{$applicationId}/kardex")
            ->assertForbidden();

        $this->withFreshToken($adminToken)->getJson('/api/admin/tutor-applications')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.student.id_usuario', $student->id_usuario)
            ->assertJsonPath('0.materias.0.id', $subject->id)
            ->assertJsonPath('0.temas_dominados', 'Álgebra y programación orientada a objetos.');

        $this->withFreshToken($adminToken)
            ->get("/api/admin/tutor-applications/{$applicationId}/kardex")
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->withFreshToken($adminToken)->patchJson("/api/admin/tutor-applications/{$applicationId}", [
            'decision' => 'RECHAZADA',
        ])->assertOk();
        $this->assertSame('ESTUDIANTE', $student->fresh()->rol);

        $this->withFreshToken($studentToken)->post('/api/tutoring/application', [
            'kardex' => UploadedFile::fake()->create('kardex-actualizado.pdf', 50, 'application/pdf'),
            'materias' => [$subject->id],
            'temas_dominados' => 'Álgebra y programación orientada a objetos.',
        ])->assertCreated();

        $this->withFreshToken($adminToken)->patchJson("/api/admin/tutor-applications/{$applicationId}", [
            'decision' => 'APROBADA',
        ])->assertOk();
        $this->assertSame('TUTOR', $student->fresh()->rol);

        $this->withFreshToken($adminToken)->putJson("/api/admin/users/{$student->id_usuario}/rol", [
            'rol' => 'TUTOR',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rol');

        $this->withFreshToken($studentToken)->getJson('/api/tutoring/profile')
            ->assertOk()
            ->assertJsonPath('carrera.nombre', $informatics->nombre)
            ->assertJsonPath('materias_ofrecidas.0.id', $subject->id)
            ->assertJsonPath('materias_ofrecidas.0.semestre', $subject->semestre->numero)
            ->assertJsonPath('temas_dominados', 'Álgebra y programación orientada a objetos.');
    }

    public function test_tutor_application_rejects_subjects_outside_the_students_career(): void
    {
        $informatics = Carrera::where('nombre', 'Licenciatura en Informática')->firstOrFail();
        $dataScience = Carrera::where('nombre', 'Licenciatura en Ingeniería en Ciencia de Datos')->firstOrFail();
        $student = $this->createUser('ESTUDIANTE', 'student@uas.edu.mx', $informatics->nombre);
        $otherCareerSubject = Materia::whereHas('semestre', fn ($query) => $query->where('carrera_id', $dataScience->id))->firstOrFail();
        $token = $student->createToken('student')->plainTextToken;

        $this->withToken($token)->post('/api/tutoring/application', [
            'kardex' => UploadedFile::fake()->create('kardex.pdf', 50, 'application/pdf'),
            'materias' => [$otherCareerSubject->id],
            'temas_dominados' => 'Análisis de datos.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('materias');

        $this->withFreshToken($token)->post('/api/tutoring/application', [
            'kardex' => UploadedFile::fake()->create('kardex.pdf', 50, 'application/pdf'),
            'materias' => [],
            'temas_dominados' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['materias', 'temas_dominados']);
    }

    public function test_session_options_explain_when_the_students_career_is_not_available(): void
    {
        $student = $this->createUser('ESTUDIANTE', 'student@uas.edu.mx', 'Carrera no registrada');
        $token = $student->createToken('student')->plainTextToken;

        $this->withToken($token)->getJson('/api/tutoring/options')
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'La carrera asociada a tu perfil no está disponible. Actualiza tu perfil o contacta al administrador.',
            );
    }

    private function createUser(string $role, string $email, string $career = 'Licenciatura en Informática'): User
    {
        return User::create([
            'nombre' => 'Ana',
            'apellido' => 'López',
            'correo_institucional' => $email,
            'password' => Hash::make('correct-password'),
            'carrera' => $career,
            'rol' => $role,
        ]);
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
