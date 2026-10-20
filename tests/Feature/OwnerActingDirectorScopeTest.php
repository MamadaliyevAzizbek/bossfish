<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerActingDirectorScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $directorA;

    private User $directorB;

    private Farm $farmA;

    private Farm $farmB;

    private Pond $pondA;

    private Pond $pondB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->directorA = User::create([
            'name' => 'Direktor A',
            'phone' => '+998901112233',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $this->directorB = User::create([
            'name' => 'Direktor B',
            'phone' => '+998901112244',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $this->farmA = Farm::create([
            'name' => 'Hojalik A',
            'region' => 'Sirdaryo',
            'director_id' => $this->directorA->id,
            'created_by' => $this->owner->id,
            'status' => 'active',
        ]);

        $this->farmB = Farm::create([
            'name' => 'Hojalik B',
            'region' => 'Toshkent',
            'director_id' => $this->directorB->id,
            'created_by' => $this->owner->id,
            'status' => 'active',
        ]);

        $this->pondA = Pond::create([
            'farm_id' => $this->farmA->id,
            'name' => 'Havza A',
            'region' => 'Sirdaryo',
            'area_m2' => 5,
            'current_stock' => 0,
            'director_id' => $this->directorA->id,
            'created_by' => $this->owner->id,
            'status' => 'active',
        ]);

        $this->pondB = Pond::create([
            'farm_id' => $this->farmB->id,
            'name' => 'Havza B',
            'region' => 'Toshkent',
            'area_m2' => 3,
            'current_stock' => 0,
            'director_id' => $this->directorB->id,
            'created_by' => $this->owner->id,
            'status' => 'active',
        ]);
    }

    public function test_owner_cannot_access_pond_without_acting_director_header(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/director/ponds/{$this->pondA->id}/summary")
            ->assertForbidden();
    }

    public function test_owner_can_access_pond_when_acting_as_correct_director(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->directorA->id)
            ->getJson("/api/director/ponds/{$this->pondA->id}/summary")
            ->assertOk();
    }

    public function test_owner_cannot_access_another_directors_pond_when_acting_as_different_director(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->directorA->id)
            ->getJson("/api/director/ponds/{$this->pondB->id}/summary")
            ->assertForbidden();
    }

    public function test_owner_farms_list_requires_acting_director(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/director/farms')
            ->assertOk()
            ->assertJsonCount(0);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->directorA->id)
            ->getJson('/api/director/farms')
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->farmA->id, $ids);
        $this->assertNotContains($this->farmB->id, $ids);
    }
}
