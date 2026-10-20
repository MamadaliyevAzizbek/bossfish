<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_with_expiration(): void
    {
        config(['auth.spa_remember_days' => 14]);

        Carbon::setTestNow('2026-09-04 12:00:00');

        User::create([
            'name' => 'Direktor',
            'phone' => '+998901234567',
            'password' => 'secret',
            'password_plain' => 'secret',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '+998901234567',
            'password' => 'secret',
            'role' => 'director',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', User::ROLE_DIRECTOR)
            ->assertJsonStructure(['token', 'expires_at']);

        $this->assertSame(
            '2026-09-18T12:00:00+05:00',
            $response->json('expires_at')
        );

        Carbon::setTestNow();
    }

    public function test_owner_login_returns_token_with_expiration(): void
    {
        config(['auth.spa_remember_days' => 30]);

        Carbon::setTestNow('2026-09-04 12:00:00');

        User::create([
            'name' => 'Egasi',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/owner-login', [
            'login' => 'ega@baliq.uz',
            'password' => 'owner-pass',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'expires_at']);

        $this->assertSame(
            '2026-10-04T12:00:00+05:00',
            $response->json('expires_at')
        );

        Carbon::setTestNow();
    }
}
