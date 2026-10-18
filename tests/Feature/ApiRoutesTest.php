<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileImageController;
use App\Models\Carrera;
use App\Models\Materia;
use App\Models\SolicitudTutoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_carreras_returns_only_active_careers_in_alphabetical_order(): void
    {
        Carrera::create(['nombre' => 'Zoología', 'activa' => true]);
        Carrera::create(['nombre' => 'Actuaría', 'activa' => true]);
        Carrera::create(['nombre' => 'Biología', 'activa' => false]);

        $careers = $this->getJson('/api/carreras')
            ->assertOk()
            ->json();

        $names = array_column($careers, 'nombre');
        $this->assertSame($names, collect($names)->sort()->values()->all());
        $this->assertContains('Actuaría', $names);
        $this->assertContains('Zoología', $names);
        $this->assertNotContains('Biología', $names);
    }

    public function test_register_creates_a_user_and_returns_a_bearer_token(): void
    {
        $response = $this->postJson('/api/register', [
            'nombre' => 'Ana',
            'apellido' => 'López',
            'correo_institucional' => 'ana@uas.edu.mx',
            'password' => 'secure-pass-123',
            'carrera' => 'Licenciatura en Informática',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id_usuario', 'correo_institucional']]);
        $this->assertDatabaseHas('users', ['correo_institucional' => 'ana@uas.edu.mx']);
    }

    public function test_login_rejects_invalid_credentials_and_returns_a_token_for_valid_credentials(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/login', [
            'correo_institucional' => $user->correo_institucional,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('correo_institucional');

        $this->postJson('/api/login', [
            'correo_institucional' => $user->correo_institucional,
            'password' => 'correct-password',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_admin_can_deactivate_and_reactivate_a_user_account(): void
    {
        $admin = $this->createUser('ADMIN', 'admin@uas.edu.mx');
        $user = $this->createUser('ESTUDIANTE', 'student@uas.edu.mx');
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $userToken = $user->createToken('student')->plainTextToken;

        $this->withToken($adminToken)
        ->patchJson("/api/admin/users/{$user->id_usuario}/status", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('user.is_active', false);

        $this->assertDatabaseHas('users', [
        'id_usuario' => $user->id_usuario,
        'is_active' => false,
        ]);
        $this->withFreshToken($userToken)->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/login', [
        'correo_institucional' => $user->correo_institucional,
        'password' => 'correct-password',
        ])->assertUnprocessable();

        $this->withFreshToken($adminToken)
        ->patchJson("/api/admin/users/{$user->id_usuario}/status", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('user.is_active', true);

        $this->postJson('/api/login', [
        'correo_institucional' => $user->correo_institucional,
        'password' => 'correct-password',
        ])->assertOk();
    }

    public function test_admin_can_create_and_update_a_career_study_plan(): void
    {
        $admin = $this->createUser('ADMIN', 'admin@uas.edu.mx');
        $token = $admin->createToken('admin')->plainTextToken;

        $creation = $this->withToken($token)->postJson('/api/admin/carreras', [
        'nombre' => 'Ingeniería de Prueba',
        'semestres' => [
            ['numero' => 1, 'materias' => ['Álgebra', 'Programación']],
            ['numero' => 2, 'materias' => ['Bases de datos']],
        ],
        ])->assertCreated()
        ->assertJsonPath('carrera.nombre', 'Ingeniería de Prueba')
        ->assertJsonCount(2, 'carrera.semestres')
        ->assertJsonCount(2, 'carrera.semestres.0.materias');

        $careerId = $creation->json('carrera.id');
        $career = Carrera::findOrFail($careerId);
        $student = $this->createUser('ESTUDIANTE', 'student@uas.edu.mx');
        $student->update(['carrera' => $career->nombre]);
        $removedSubject = Materia::where('nombre', 'Programación')->firstOrFail();
        $request = SolicitudTutoria::create([
        'estudiante_id' => $student->id_usuario,
        'tutor_id' => $admin->id_usuario,
        'materia_id' => $removedSubject->id,
        'fecha_hora' => now()->addDays(2),
        'estado' => 'PENDIENTE',
        ]);

        $this->withFreshToken($token)->putJson("/api/admin/carreras/{$careerId}", [
        'nombre' => 'Ingeniería de Prueba Actualizada',
        'semestres' => [
            ['numero' => 1, 'materias' => ['Álgebra', 'Programación corregida']],
            ['numero' => 3, 'materias' => ['Inteligencia artificial']],
        ],
        ])->assertOk()
        ->assertJsonPath('carrera.nombre', 'Ingeniería de Prueba Actualizada')
        ->assertJsonCount(2, 'carrera.semestres')
        ->assertJsonPath('carrera.semestres.1.numero', 3);
        $this->assertDatabaseHas('materias', [
        'semestre_id' => $career->semestres()->where('numero', 1)->value('id'),
        'nombre' => 'Programación corregida',
        ]);

        $this->assertDatabaseHas('users', [
        'id_usuario' => $student->id_usuario,
        'carrera' => 'Ingeniería de Prueba Actualizada',
        ]);
        $this->assertDatabaseHas('solicitudes_tutoria', [
        'id' => $request->id,
        'materia_id' => null,
        'materia_nombre' => 'Programación',
        ]);
    }

    public function test_admin_study_plan_validation_rejects_duplicate_semester_numbers(): void
    {
        $admin = $this->createUser('ADMIN', 'admin@uas.edu.mx');
        $token = $admin->createToken('admin')->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/carreras', [
        'nombre' => 'Carrera con semestre duplicado',
        'semestres' => [
            ['numero' => 1, 'materias' => ['Materia A']],
            ['numero' => 1, 'materias' => ['Materia B']],
        ],
        ])->assertUnprocessable()
        ->assertJsonValidationErrors('semestres.1.numero');
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->createUser('ADMIN', 'admin@uas.edu.mx');
        $token = $admin->createToken('admin')->plainTextToken;

        $this->withToken($token)
        ->patchJson("/api/admin/users/{$admin->id_usuario}/status", ['is_active' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_active');

        $this->assertDatabaseHas('users', [
        'id_usuario' => $admin->id_usuario,
        'is_active' => true,
        ]);
    }

    public function test_protected_routes_require_a_valid_bearer_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/user/image')->assertUnauthorized();
        $this->deleteJson('/api/user/image')->assertUnauthorized();
        $this->putJson('/api/user/password')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->getJson('/api/tutoring/options')->assertUnauthorized();
        $this->getJson('/api/tutoring/requests')->assertUnauthorized();
        $this->postJson('/api/tutoring/requests')->assertUnauthorized();
        $this->postJson('/api/tutoring/requests/1/rating')->assertUnauthorized();
    }

    public function test_authenticated_user_can_delete_their_profile_image(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;
        $this->mock(ProfileImageController::class)
            ->shouldReceive('destroy')
            ->once()
            ->andReturn(response()->json([
                'message' => 'Fotografía eliminada correctamente.',
            ]));

        $this->withFreshToken($token)->deleteJson('/api/user/image')
            ->assertOk()
            ->assertJsonPath('message', 'Fotografía eliminada correctamente.');
    }

    public function test_authenticated_user_route_returns_the_token_owner(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;
        $this->mock(ProfileImageController::class)
            ->shouldReceive('urlFor')
            ->once()
            ->andReturn(null);

        $this->withFreshToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id_usuario', $user->id_usuario)
            ->assertJsonPath('correo_institucional', $user->correo_institucional);
    }

    public function test_password_route_requires_current_password_and_confirmation(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withFreshToken($token)->putJson('/api/user/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->withFreshToken($token)->putJson('/api/user/password', [
            'current_password' => 'correct-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->withFreshToken($token)->putJson('/api/user/password', [
            'current_password' => 'correct-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath('message', 'Contraseña actualizada correctamente.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_logout_revokes_the_current_bearer_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;
        $accessToken = $user->tokens()->firstOrFail();

        $this->withFreshToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->id]);
    }

    public function test_profile_image_routes_reject_invalid_signature_and_file_type(): void
    {
        $this->getJson('/api/user/image/1')->assertForbidden();

        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withFreshToken($token)->postJson('/api/user/image', [
            'image' => UploadedFile::fake()->create('document.txt', 1, 'text/plain'),
        ]);

        if (! class_exists(\MongoDB\Driver\Manager::class)) {
            $response->assertInternalServerError()
                ->assertJsonPath('message', 'La extensión MongoDB no está habilitada en este entorno.');

            return;
        }

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_student_can_create_and_track_a_tutoring_request_and_admin_can_supervise_it(): void
    {
        $student = $this->createUser('ESTUDIANTE', 'ana@uas.edu.mx');
        $otherStudent = $this->createUser('ESTUDIANTE', 'otra@uas.edu.mx');
        $tutor = $this->createUser('TUTOR', 'tutor@uas.edu.mx');
        $admin = $this->createUser('ADMIN', 'admin@uas.edu.mx');
        $subject = $this->createSubject('Álgebra');

        $studentToken = $student->createToken('student')->plainTextToken;
        $otherStudentToken = $otherStudent->createToken('other-student')->plainTextToken;
        $tutorToken = $tutor->createToken('tutor')->plainTextToken;
        $adminToken = $admin->createToken('admin')->plainTextToken;

        $response = $this->withFreshToken($studentToken)->postJson('/api/tutoring/requests', [
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'mensaje' => 'Necesito repasar ecuaciones.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('solicitud.estado', 'PENDIENTE')
            ->assertJsonPath('solicitud.estudiante.id_usuario', $student->id_usuario)
            ->assertJsonPath('solicitud.tutor.id_usuario', $tutor->id_usuario)
            ->assertJsonPath('solicitud.materia.id', $subject->id);

        $requestId = $response->json('solicitud.id');

        $this->assertDatabaseHas('solicitudes_tutoria', [
            'id' => $requestId,
            'estudiante_id' => $student->id_usuario,
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'estado' => 'PENDIENTE',
        ]);

        $this->withFreshToken($otherStudentToken)->getJson('/api/tutoring/requests')
            ->assertOk()
            ->assertJsonCount(0);

        $this->withFreshToken($tutorToken)->getJson('/api/tutoring/requests')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $requestId);

        $this->withFreshToken($tutorToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'ACEPTADA',
        ])->assertOk()
            ->assertJsonPath('solicitud.estado', 'ACEPTADA');

        $this->withFreshToken($tutorToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'COMPLETADA',
        ])->assertOk()
            ->assertJsonPath('solicitud.estado', 'COMPLETADA');

        $this->withFreshToken($studentToken)->getJson('/api/tutoring/requests')
            ->assertOk()
            ->assertJsonPath('0.estado', 'COMPLETADA');

        $this->withFreshToken($adminToken)->getJson('/api/tutoring/requests')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $requestId);
    }

    public function test_tutoring_request_permissions_and_status_transitions_are_enforced(): void
    {
        $student = $this->createUser('ESTUDIANTE', 'ana@uas.edu.mx');
        $anotherStudent = $this->createUser('ESTUDIANTE', 'otra@uas.edu.mx');
        $tutor = $this->createUser('TUTOR', 'tutor@uas.edu.mx');
        $anotherTutor = $this->createUser('TUTOR', 'otro-tutor@uas.edu.mx');
        $admin = $this->createUser('ADMIN', 'admin@uas.edu.mx');
        $subject = $this->createSubject('Álgebra');
        $inactiveSubject = $this->createSubject('Física', 'Otra carrera');

        $studentToken = $student->createToken('student')->plainTextToken;
        $anotherStudentToken = $anotherStudent->createToken('other-student')->plainTextToken;
        $tutorToken = $tutor->createToken('tutor')->plainTextToken;
        $anotherTutorToken = $anotherTutor->createToken('other-tutor')->plainTextToken;
        $adminToken = $admin->createToken('admin')->plainTextToken;

        $this->withFreshToken($studentToken)->getJson('/api/tutoring/options')
            ->assertOk()
            ->assertJsonCount(1, 'materias')
            ->assertJsonCount(2, 'tutores');

        $invalidRequest = [
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $inactiveSubject->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ];
        $this->withFreshToken($studentToken)->postJson('/api/tutoring/requests', $invalidRequest)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('materia_id');

        $invalidRequest['materia_id'] = $subject->id;
        $invalidRequest['tutor_id'] = $student->id_usuario;
        $this->withFreshToken($studentToken)->postJson('/api/tutoring/requests', $invalidRequest)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tutor_id');

        $validRequest = [
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ];
        $requestId = $this->withFreshToken($studentToken)
            ->postJson('/api/tutoring/requests', $validRequest)
            ->assertCreated()
            ->json('solicitud.id');

        $this->withFreshToken($anotherStudentToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'CANCELADA',
        ])->assertForbidden();

        $this->withFreshToken($anotherTutorToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'ACEPTADA',
        ])->assertForbidden();

        $this->withFreshToken($adminToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'RECHAZADA',
        ])->assertForbidden();

        $this->withFreshToken($studentToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'ACEPTADA',
        ])->assertUnprocessable();

        $this->withFreshToken($tutorToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'ACEPTADA',
        ])->assertOk();

        $this->withFreshToken($tutorToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'RECHAZADA',
        ])->assertUnprocessable();

        $this->withFreshToken($studentToken)->patchJson("/api/tutoring/requests/{$requestId}/status", [
            'estado' => 'CANCELADA',
        ])->assertOk()
            ->assertJsonPath('solicitud.estado', 'CANCELADA');

        $pastAcceptedRequest = SolicitudTutoria::create([
            'estudiante_id' => $student->id_usuario,
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->subMinute(),
            'estado' => 'ACEPTADA',
        ]);

        $this->withFreshToken($studentToken)->patchJson(
            "/api/tutoring/requests/{$pastAcceptedRequest->id}/status",
            ['estado' => 'CANCELADA']
        )->assertUnprocessable();

        $pastPendingRequest = SolicitudTutoria::create([
            'estudiante_id' => $student->id_usuario,
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->subMinute(),
            'estado' => 'PENDIENTE',
        ]);

        $this->withFreshToken($tutorToken)->patchJson(
            "/api/tutoring/requests/{$pastPendingRequest->id}/status",
            ['estado' => 'ACEPTADA']
        )->assertUnprocessable();
    }

    public function test_student_can_cancel_a_pending_request_and_tutor_can_reject_one(): void
    {
        $student = $this->createUser('ESTUDIANTE', 'ana@uas.edu.mx');
        $tutor = $this->createUser('TUTOR', 'tutor@uas.edu.mx');
        $subject = $this->createSubject('Álgebra');
        $studentToken = $student->createToken('student')->plainTextToken;
        $tutorToken = $tutor->createToken('tutor')->plainTextToken;
        $requestData = [
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ];

        $cancelledId = $this->withFreshToken($studentToken)
            ->postJson('/api/tutoring/requests', $requestData)
            ->assertCreated()
            ->json('solicitud.id');

        $this->withFreshToken($studentToken)->patchJson("/api/tutoring/requests/{$cancelledId}/status", [
            'estado' => 'CANCELADA',
        ])->assertOk()
            ->assertJsonPath('solicitud.estado', 'CANCELADA');

        $rejectedId = $this->withFreshToken($studentToken)
            ->postJson('/api/tutoring/requests', $requestData)
            ->assertCreated()
            ->json('solicitud.id');

        $this->withFreshToken($tutorToken)->patchJson("/api/tutoring/requests/{$rejectedId}/status", [
            'estado' => 'RECHAZADA',
        ])->assertOk()
            ->assertJsonPath('solicitud.estado', 'RECHAZADA');
    }

    public function test_student_can_rate_a_completed_tutoring_request_only_once(): void
    {
        $student = $this->createUser('ESTUDIANTE', 'ana@uas.edu.mx');
        $anotherStudent = $this->createUser('ESTUDIANTE', 'otra@uas.edu.mx');
        $tutor = $this->createUser('TUTOR', 'tutor@uas.edu.mx');
        $subject = $this->createSubject('Álgebra');
        $studentToken = $student->createToken('student')->plainTextToken;
        $anotherStudentToken = $anotherStudent->createToken('other-student')->plainTextToken;
        $completedRequest = SolicitudTutoria::create([
            'estudiante_id' => $student->id_usuario,
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->subDay(),
            'estado' => 'COMPLETADA',
        ]);
        $pendingRequest = SolicitudTutoria::create([
            'estudiante_id' => $student->id_usuario,
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->addDay(),
            'estado' => 'PENDIENTE',
        ]);
        $otherStudentRequest = SolicitudTutoria::create([
            'estudiante_id' => $anotherStudent->id_usuario,
            'tutor_id' => $tutor->id_usuario,
            'materia_id' => $subject->id,
            'fecha_hora' => now()->subDay(),
            'estado' => 'COMPLETADA',
        ]);

        $this->withFreshToken($studentToken)->postJson(
            "/api/tutoring/requests/{$pendingRequest->id}/rating",
            ['calificacion' => 5]
        )->assertUnprocessable();

        $this->withFreshToken($studentToken)->postJson(
            "/api/tutoring/requests/{$otherStudentRequest->id}/rating",
            ['calificacion' => 5]
        )->assertForbidden();

        $this->withFreshToken($studentToken)->postJson(
            "/api/tutoring/requests/{$completedRequest->id}/rating",
            ['calificacion' => 6]
        )->assertUnprocessable()
            ->assertJsonValidationErrors('calificacion');

        $this->withFreshToken($studentToken)->postJson(
            "/api/tutoring/requests/{$completedRequest->id}/rating",
            [
                'calificacion' => 5,
                'comentario_calificacion' => 'Explicó el tema con claridad.',
            ]
        )->assertOk()
            ->assertJsonPath('solicitud.calificacion', 5)
            ->assertJsonPath('solicitud.comentario_calificacion', 'Explicó el tema con claridad.');

        $this->withFreshToken($studentToken)->postJson(
            "/api/tutoring/requests/{$completedRequest->id}/rating",
            ['calificacion' => 4]
        )->assertUnprocessable();

        $this->assertDatabaseHas('solicitudes_tutoria', [
            'id' => $completedRequest->id,
            'calificacion' => 5,
            'comentario_calificacion' => 'Explicó el tema con claridad.',
        ]);
    }

    /**
     * Limpia el usuario cacheado por el guard antes de simular otro token en la misma prueba.
     */
    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function createUser(string $role = 'ESTUDIANTE', string $email = 'ana@uas.edu.mx'): User
    {
        return User::create([
            'nombre' => 'Ana',
            'apellido' => 'López',
            'correo_institucional' => $email,
            'password' => Hash::make('correct-password'),
            'carrera' => 'Actuaría',
            'rol' => $role,
        ]);
    }

    private function createSubject(string $name, string $careerName = 'Actuaría'): \App\Models\Materia
    {
        $career = Carrera::firstOrCreate(
            ['nombre' => $careerName],
            ['activa' => true],
        );
        $semester = \App\Models\Semestre::firstOrCreate(
            ['carrera_id' => $career->id, 'numero' => 1],
        );

        return \App\Models\Materia::firstOrCreate(
            ['semestre_id' => $semester->id, 'nombre' => $name],
        );
    }
}
