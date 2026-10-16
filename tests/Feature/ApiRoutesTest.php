<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileImageController;
use App\Models\Carrera;
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

        $this->getJson('/api/carreras')
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Actuaría')
            ->assertJsonPath('1.nombre', 'Zoología')
            ->assertJsonCount(2);
    }

    public function test_register_creates_a_user_and_returns_a_bearer_token(): void
    {
        Carrera::create(['nombre' => 'Actuaría']);

        $response = $this->postJson('/api/register', [
            'nombre' => 'Ana',
            'apellido' => 'López',
            'correo_institucional' => 'ana@uas.edu.mx',
            'password' => 'secure-pass-123',
            'carrera' => 'Actuaría',
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

    public function test_protected_routes_require_a_valid_bearer_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/user/image')->assertUnauthorized();
        $this->deleteJson('/api/user/image')->assertUnauthorized();
        $this->putJson('/api/user/password')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
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

        $this->withToken($token)->deleteJson('/api/user/image')
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

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id_usuario', $user->id_usuario)
            ->assertJsonPath('correo_institucional', $user->correo_institucional);
    }

    public function test_password_route_requires_current_password_and_confirmation(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/user/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->withToken($token)->putJson('/api/user/password', [
            'current_password' => 'correct-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->withToken($token)->putJson('/api/user/password', [
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

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->id]);
    }

    public function test_profile_image_routes_reject_invalid_signature_and_file_type(): void
    {
        $this->getJson('/api/user/image/1')->assertForbidden();

        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/user/image', [
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

    private function createUser(): User
    {
        return User::create([
            'nombre' => 'Ana',
            'apellido' => 'López',
            'correo_institucional' => 'ana@uas.edu.mx',
            'password' => Hash::make('correct-password'),
            'carrera' => 'Actuaría',
            'rol' => 'ESTUDIANTE',
        ]);
    }
}
