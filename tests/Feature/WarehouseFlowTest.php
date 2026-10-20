<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseFlowTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithPond(): array
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112233',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
        ]);

        $farm = Farm::create([
            'name' => 'Test hojalik',
            'region' => 'Sirdaryo',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        $pond = Pond::create([
            'farm_id' => $farm->id,
            'name' => 'Test havza',
            'region' => 'Sirdaryo',
            'area_m2' => 1000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        return [$director, $farm, $pond];
    }

    private function addFeedViaFinance(User $director, Farm $farm, string $name, float $quantity): array
    {
        return $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'feed',
                'title' => $name,
                'expense_date' => '2026-08-09',
                'quantity' => $quantity,
                'unit_price' => 3500,
            ])
            ->assertCreated()
            ->json();
    }

    private function addMedicineViaFinance(User $director, Farm $farm, string $name, float $quantity): array
    {
        return $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'medicine',
                'title' => $name,
                'expense_date' => '2026-08-09',
                'quantity' => $quantity,
                'unit_price' => 50000,
            ])
            ->assertCreated()
            ->json();
    }

    public function test_finance_feed_adds_stock_and_daily_report_consumes_it(): void
    {
        [$director, $farm, $pond] = $this->directorWithPond();

        $this->addFeedViaFinance($director, $farm, 'Granula 35', 200);
        $this->addMedicineViaFinance($director, $farm, 'Amafos', 50);

        $feed = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', 'Granula 35')
            ->firstOrFail();

        $medicine = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', 'Amafos')
            ->firstOrFail();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-08-09',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 120.5,
                'fertilizer_kg' => 15,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $feed->id,
            'quantity' => 79.5,
        ]);

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $medicine->id,
            'quantity' => 35,
        ]);

        $this->assertDatabaseHas('daily_reports', [
            'pond_id' => $pond->id,
            'feeding_kg' => 120.5,
            'fertilizer_kg' => 15,
        ]);
    }

    public function test_consumable_intake_is_blocked(): void
    {
        [$director, $farm] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/intake", [
                'feed_name' => 'Maxsus yem 40',
                'feed_kg' => 25,
                'wheat_kg' => 10,
                'fertilizer_kg' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intake']);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/intake", [
                'medicine_name' => 'Antibiotik',
                'medicine_unit' => 'piece',
                'medicine_qty' => 12,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intake']);
    }

    public function test_director_can_upload_photo_when_adding_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithPond();

        $response = $this->actingAs($director, 'sanctum')
            ->post("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'equipment',
                'name' => 'Skuter',
                'unit' => 'piece',
                'quantity' => 1,
                'photo' => \Illuminate\Http\UploadedFile::fake()->image('skuter.jpg'),
            ])
            ->assertCreated();

        $photoPath = $response->json('photo_url');
        $this->assertNotNull($photoPath);
        $this->assertStringContainsString('/storage/warehouse/', $photoPath);

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'name' => 'Skuter',
        ]);
    }

    public function test_worker_can_submit_daily_report_for_assigned_farm_pond(): void
    {
        [$director, $farm, $pond] = $this->directorWithPond();

        $worker = User::create([
            'name' => 'Hodim',
            'phone' => '+998901110099',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_WORKER,
        ]);
        $worker->assignedFarms()->attach($farm->id);

        $this->addFeedViaFinance($director, $farm, "Bug'doy", 100);

        $item = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', "Bug'doy")
            ->firstOrFail();

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/worker/me')
            ->assertOk()
            ->assertJsonPath('farm.id', $farm->id)
            ->assertJsonPath('pond.id', $pond->id);

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/worker/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-08-09',
                'feed_name' => "Bug'doy",
                'feeding_kg' => 25,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $item->id,
            'quantity' => 75,
        ]);
    }
}
