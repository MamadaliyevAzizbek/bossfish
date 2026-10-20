<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorAccessDaysTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Egasi',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
    }

    public function test_director_created_with_access_days_and_login_works(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/directors', [
                'name' => 'Direktor',
                'phone' => '+998901112288',
                'password' => 'dir1234',
                'access_days' => 59,
            ])
            ->assertCreated();

        $this->assertNotNull($response->json('access_expires_at'));
        $this->assertSame(58, $response->json('access_days_left')); // floor of ~59 days from now

        $this->postJson('/api/auth/login', [
            'phone' => '+998901112288',
            'password' => 'dir1234',
            'role' => 'director',
        ])->assertOk();
    }

    public function test_director_created_without_access_days_gets_default_plan_and_can_login(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/directors', [
                'name' => 'Yangi mijoz',
                'phone' => '+998901112299',
                'password' => 'dir1234',
            ])
            ->assertCreated()
            ->assertJsonPath('subscription.plan', 'cabinet')
            ->assertJsonPath('access_expired', false);

        $this->assertNotNull($response->json('subscription.id'));

        $this->postJson('/api/auth/login', [
            'phone' => '+998901112299',
            'password' => 'dir1234',
            'role' => 'director',
        ])->assertOk();
    }

    public function test_expired_director_cannot_login_until_days_added(): void
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112299',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/auth/login', [
            'phone' => '+998901112299',
            'password' => 'dir1234',
            'role' => 'director',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/directors/{$director->id}", [
                'extend_access_days' => 30,
            ])
            ->assertOk();

        $this->postJson('/api/auth/login', [
            'phone' => '+998901112299',
            'password' => 'dir1234',
            'role' => 'director',
        ])->assertOk();
    }

    public function test_security_lists_workers_and_expiring_directors(): void
    {
        User::create([
            'name' => 'Direktor',
            'phone' => '+998901112300',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(7),
        ]);

        User::create([
            'name' => 'Hodim',
            'phone' => '+998901112301',
            'password' => 'work12',
            'password_plain' => 'work12',
            'role' => User::ROLE_WORKER,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/security')
            ->assertOk()
            ->assertJsonCount(1, 'directors')
            ->assertJsonCount(1, 'workers')
            ->assertJsonCount(1, 'expiring_directors')
            ->assertJsonPath('workers.0.password', 'work12');
    }

    public function test_extend_adds_days_to_existing_expiry(): void
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112302',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(20),
        ]);

        $before = $director->access_expires_at->copy();

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/directors/{$director->id}", [
                'extend_access_days' => 10,
            ])
            ->assertOk();

        $this->assertTrue($director->fresh()->access_expires_at->equalTo($before->addDays(10)));
    }
}
