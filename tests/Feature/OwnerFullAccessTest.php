<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\Farm;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerFullAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $director;

    private Pond $pond;

    private Farm $farm;

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

        $this->director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112266',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $this->farm = Farm::create([
            'name' => 'Test hojalik',
            'region' => 'Toshkent',
            'director_id' => $this->director->id,
            'created_by' => $this->owner->id,
            'status' => 'active',
        ]);

        $this->pond = Pond::create([
            'farm_id' => $this->farm->id,
            'name' => 'Test havza',
            'region' => 'Toshkent',
            'area_m2' => 1000,
            'current_stock' => 0,
            'director_id' => $this->director->id,
            'created_by' => $this->owner->id,
            'status' => 'active',
        ]);
    }

    public function test_owner_can_list_all_ponds_and_open_director_routes(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->id)
            ->getJson('/api/director/ponds')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->pond->id]);

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->id)
            ->getJson("/api/director/ponds/{$this->pond->id}/summary")
            ->assertOk();
    }

    public function test_owner_can_edit_daily_report_feed_amount(): void
    {
        WarehouseItem::create([
            'farm_id' => $this->farm->id,
            'category' => 'feed',
            'name' => 'Granula 35',
            'unit' => 'kg',
            'quantity' => 500,
            'created_by' => $this->director->id,
        ]);

        WarehouseItem::create([
            'farm_id' => $this->farm->id,
            'category' => 'feed',
            'name' => 'Kepak',
            'unit' => 'kg',
            'quantity' => 100,
            'created_by' => $this->director->id,
        ]);

        WarehouseItem::create([
            'farm_id' => $this->farm->id,
            'category' => 'medicine',
            'name' => 'Amafos',
            'unit' => 'kg',
            'quantity' => 50,
            'created_by' => $this->director->id,
        ]);

        $report = DailyReport::create([
            'pond_id' => $this->pond->id,
            'user_id' => $this->director->id,
            'report_date' => '2026-07-30',
            'feed_name' => 'Granula 35',
            'feeding_kg' => 10,
            'wheat_bran_kg' => 2,
            'fertilizer_kg' => 1,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->id)
            ->putJson("/api/director/ponds/{$this->pond->id}/daily-reports/{$report->id}", [
                'report_date' => '2026-07-30',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 15.5,
                'wheat_bran_kg' => 2,
                'fertilizer_kg' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('feeding_kg', '15.50');

        $this->assertEquals(15.5, (float) $report->fresh()->feeding_kg);
    }

    public function test_owner_can_update_fish_add_and_stock_recalculates(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->id)
            ->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 100,
                'average_weight_g' => 200,
                'operated_at' => '2026-07-01',
            ])
            ->assertCreated();

        $operation = FishOperation::first();

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->id)
            ->putJson("/api/director/ponds/{$this->pond->id}/fish-operations/{$operation->id}", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 150,
                'average_weight_g' => 250,
                'operated_at' => '2026-07-01',
            ])
            ->assertOk()
            ->assertJsonPath('current_stock', 150);

        $this->assertSame(150, (int) $this->pond->fresh()->current_stock);
        $this->assertEquals(0.25, (float) $operation->fresh()->average_weight_kg);
    }

    public function test_director_cannot_access_another_directors_pond(): void
    {
        $other = User::create([
            'name' => 'Boshqa',
            'phone' => '+998901112277',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/director/ponds/{$this->pond->id}/summary")
            ->assertForbidden();
    }
}
