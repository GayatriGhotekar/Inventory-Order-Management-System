<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SecurityReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'limited@example.com', 'password' => Hash::make('correct-password')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'limited@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'limited@example.com', 'password' => 'wrong-password'])
            ->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_login_token_has_an_expiration_time(): void
    {
        User::factory()->create(['email' => 'expires@example.com', 'password' => Hash::make('password')]);

        $this->postJson('/api/login', ['email' => 'expires@example.com', 'password' => 'password'])->assertOk();

        $token = PersonalAccessToken::firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_security_headers_are_added_to_browser_and_api_responses(): void
    {
        foreach (['/', '/api/health'] as $path) {
            $this->get($path)->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }
    }
}
