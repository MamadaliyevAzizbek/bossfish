<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PondMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_pond_with_coordinates_and_dashboard_returns_map_data(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $director = User::create([
            'name' => 'Ali Direktor',
            'phone' => '+998901110099',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $farm = Farm::create([
            'name' => 'Asosiy hojalik',
            'region' => 'Toshkent viloyati',
            'director_id' => $director->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/ponds', [
                'farm_id' => $farm->id,
                'name' => 'Tosh havza',
                'region' => 'Toshkent viloyati',
                'district' => 'Qibray tumani',
                'area_m2' => 12.5,
            ])
            ->assertCreated()
            ->assertJsonPath('region', 'Toshkent viloyati')
            ->assertJsonPath('district', 'Qibray tumani');

        Pond::query()->where('name', 'Tosh havza')->update([
            'latitude' => 41.12000,
            'longitude' => 69.45000,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'map_ponds')
            ->assertJsonPath('map_ponds.0.name', 'Tosh havza')
            ->assertJsonPath('map_ponds.0.director.name', 'Ali Direktor')
            ->assertJsonPath('by_region.0.ponds_count', 1)
            ->assertJsonPath('by_region.0.lat', 41.02);
    }

    public function test_regions_endpoint_returns_coordinates(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/regions')
            ->assertOk()
            ->assertJsonStructure([['name', 'lat', 'lng']])
            ->assertJsonFragment(['name' => 'Samarqand', 'lat' => 39.654, 'lng' => 66.96]);
    }
}
