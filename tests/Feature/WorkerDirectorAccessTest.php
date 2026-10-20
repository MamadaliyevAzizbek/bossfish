<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerDirectorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_login_while_director_access_is_active(): void
    {
        [$director, $farm, $worker] = $this->createWorkerWithDirector([
            'director' => ['access_expires_at' => now()->addDays(30)],
        ]);

        $this->postJson('/api/auth/login', [
            'phone' => $worker->phone,
            'password' => 'work1234',
            'role' => 'worker',
        ])->assertOk();

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/worker/me')
            ->assertOk()
            ->assertJsonPath('director_access_blocked', false)
            ->assertJsonPath('linked_director.id', $director->id);
    }

    public function test_worker_cannot_login_when_director_access_expired(): void
    {
        [, , $worker] = $this->createWorkerWithDirector([
            'director' => ['access_expires_at' => now()->subDay()],
        ]);

        $this->postJson('/api/auth/login', [
            'phone' => $worker->phone,
            'password' => 'work1234',
            'role' => 'worker',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_worker_cannot_login_when_director_is_inactive(): void
    {
        [, , $worker] = $this->createWorkerWithDirector([
            'director' => ['is_active' => false],
        ]);

        $this->postJson('/api/auth/login', [
            'phone' => $worker->phone,
            'password' => 'work1234',
            'role' => 'worker',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_worker_with_director_only_can_login_without_farm_pivot(): void
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901113333',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(30),
        ]);

        $worker = User::create([
            'name' => 'Hodim',
            'phone' => '+998901114444',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'role' => User::ROLE_WORKER,
            'is_active' => true,
            'director_id' => $director->id,
        ]);

        $this->postJson('/api/auth/login', [
            'phone' => $worker->phone,
            'password' => 'work1234',
            'role' => 'worker',
        ])->assertOk();
    }

    public function test_worker_api_blocked_when_director_access_expires(): void
    {
        [$director, , $worker] = $this->createWorkerWithDirector([
            'director' => ['access_expires_at' => now()->addDay()],
        ]);

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/worker/me')
            ->assertOk();

        $director->update(['access_expires_at' => now()->subMinute()]);

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/worker/me')
            ->assertForbidden();
    }

    public function test_worker_login_works_again_after_director_access_extended(): void
    {
        [$director, , $worker] = $this->createWorkerWithDirector([
            'director' => ['access_expires_at' => now()->subDay()],
        ]);

        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/owner/directors/{$director->id}", [
                'extend_access_days' => 30,
            ])
            ->assertOk();

        $this->postJson('/api/auth/login', [
            'phone' => $worker->phone,
            'password' => 'work1234',
            'role' => 'worker',
        ])->assertOk();
    }

    /** @return array{0: User, 1: Farm, 2: User} */
    private function createWorkerWithDirector(array $overrides = []): array
    {
        $director = User::create(array_merge([
            'name' => 'Direktor',
            'phone' => '+998901112233',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(30),
        ], $overrides['director'] ?? []));

        $farm = Farm::create(array_merge([
            'name' => 'Test hojalik',
            'region' => 'Sirdaryo',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ], $overrides['farm'] ?? []));

        $worker = User::create(array_merge([
            'name' => 'Hodim',
            'phone' => '+998901112244',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'role' => User::ROLE_WORKER,
            'is_active' => true,
            'created_by' => $director->id,
            'director_id' => $director->id,
        ], $overrides['worker'] ?? []));

        $worker->assignedFarms()->attach($farm->id);

        return [$director, $farm, $worker];
    }
}
