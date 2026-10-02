<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_suite_uses_the_isolated_sqlite_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Staff User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'staff')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'role' => 'staff',
        ]);
    }

    public function test_user_can_login_and_view_profile(): void
    {
        User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'staff@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        $loginResponse->assertOk()->assertJsonPath('success', true);

        $this->withToken($token)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.email', 'staff@example.com');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        $this->postJson('/api/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_role_middleware_blocks_users_without_an_allowed_role(): void
    {
        Route::middleware(['auth:sanctum', 'role:admin'])
            ->get('/api/test-admin-route', fn () => response()->json(['success' => true]));

        $staffUser = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staffUser);

        $this->getJson('/api/test-admin-route')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
