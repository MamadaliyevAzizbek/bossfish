<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerFarmAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_farm_for_director(): void
    {
        $owner = User::create([
            'name' => 'Egasi',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112233',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/farms', [
                'name' => 'Asosiy hojalik',
                'region' => 'Toshkent viloyati',
                'director_id' => $director->id,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Asosiy hojalik')
            ->assertJsonPath('director.id', $director->id);

        $this->assertDatabaseHas('farms', [
            'name' => 'Asosiy hojalik',
            'director_id' => $director->id,
        ]);
    }

    public function test_owner_cannot_create_farm_without_director(): void
    {
        $owner = User::create([
            'name' => 'Egasi',
            'email' => 'ega2@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/farms', [
                'name' => 'Direktorsiz hojalik',
                'region' => 'Toshkent viloyati',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['director_id']);
    }

    public function test_owner_cannot_assign_second_farm_to_director(): void
    {
        $owner = User::create([
            'name' => 'Egasi',
            'email' => 'ega3@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112277',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
        ]);

        Farm::create([
            'name' => 'Birinchi hojalik',
            'region' => 'Toshkent viloyati',
            'director_id' => $director->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/farms', [
                'name' => 'Ikkinchi hojalik',
                'region' => 'Toshkent viloyati',
                'director_id' => $director->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['director_id']);
    }

    public function test_director_sees_only_assigned_farms_and_cannot_create_farm(): void
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112244',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
        ]);

        $otherDirector = User::create([
            'name' => 'Boshqa',
            'phone' => '+998901112255',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
        ]);

        $mine = Farm::create([
            'name' => 'Mening hojaligim',
            'region' => 'Toshkent',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        Farm::create([
            'name' => 'Begona hojalik',
            'region' => 'Samarqand',
            'director_id' => $otherDirector->id,
            'created_by' => $otherDirector->id,
            'status' => 'active',
        ]);

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/director/farms')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);

        $this->actingAs($director, 'sanctum')
            ->postJson('/api/director/farms', [
                'name' => 'Yangi',
                'region' => 'Toshkent viloyati',
            ])
            ->assertMethodNotAllowed();
    }

    public function test_director_cannot_create_worker(): void
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112266',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson('/api/owner/workers', [
                'name' => 'Hodim',
                'phone' => '+998901110099',
                'password' => '1234',
            ])
            ->assertForbidden();
    }
}
