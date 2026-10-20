<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerWorkerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_must_assign_worker_to_director(): void
    {
        [$owner] = $this->seedOwnerDirectorFarm();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/workers', [
                'name' => 'Hodim',
                'phone' => '+998901112244',
                'password' => 'work1234',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['director_id']);
    }

    public function test_owner_creates_worker_with_director_only(): void
    {
        [$owner, $director, $farm] = $this->seedOwnerDirectorFarm();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/workers', [
                'name' => 'Hodim',
                'phone' => '+998901112244',
                'password' => 'work1234',
                'director_id' => $director->id,
            ])
            ->assertCreated()
            ->assertJsonPath('director_id', $director->id)
            ->assertJsonPath('director.id', $director->id)
            ->assertJsonPath('assigned_farms.0.id', $farm->id);

        $this->assertDatabaseHas('users', [
            'phone' => '+998901112244',
            'role' => User::ROLE_WORKER,
            'director_id' => $director->id,
        ]);

        $this->assertDatabaseHas('farm_worker', [
            'farm_id' => $farm->id,
        ]);
    }

    public function test_owner_creates_worker_without_farm_when_director_has_none(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss2@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112255',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(30),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/workers', [
                'name' => 'Hodim',
                'phone' => '+998901112266',
                'password' => 'work1234',
                'director_id' => $director->id,
            ])
            ->assertCreated()
            ->assertJsonPath('director_id', $director->id)
            ->assertJsonPath('assigned_farms', []);

        $this->postJson('/api/auth/login', [
            'phone' => '+998901112266',
            'password' => 'work1234',
            'role' => 'worker',
        ])->assertOk();
    }

    public function test_unassigned_worker_cannot_login(): void
    {
        User::create([
            'name' => 'Hodim',
            'phone' => '+998901112244',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'role' => User::ROLE_WORKER,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'phone' => '+998901112244',
            'password' => 'work1234',
            'role' => 'worker',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_new_director_can_login_immediately(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss3@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/directors', [
                'name' => 'Yangi Direktor',
                'phone' => '+998901119999',
                'password' => 'dir9999',
                'access_days' => 90,
            ])
            ->assertCreated()
            ->assertJsonPath('is_active', true);

        $this->postJson('/api/auth/login', [
            'phone' => '+998901119999',
            'password' => 'dir9999',
            'role' => 'director',
        ])->assertOk();
    }

    /** @return array{0: User, 1: User, 2: Farm} */
    private function seedOwnerDirectorFarm(): array
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112233',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(30),
            'created_by' => $owner->id,
        ]);

        $farm = Farm::create([
            'name' => 'Test hojalik',
            'region' => 'Sirdaryo',
            'director_id' => $director->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        return [$owner, $director, $farm];
    }
}
