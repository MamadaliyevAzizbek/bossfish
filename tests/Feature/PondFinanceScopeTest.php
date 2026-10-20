<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PondFinanceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function setupFarm(): array
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901119900',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(300),
        ]);

        $farm = Farm::create([
            'name' => 'Test farm',
            'region' => 'Toshkent',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        $pondA = Pond::create([
            'farm_id' => $farm->id,
            'name' => 'Havza A',
            'region' => 'Toshkent',
            'area_m2' => 1000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        $pondB = Pond::create([
            'farm_id' => $farm->id,
            'name' => 'Havza B',
            'region' => 'Toshkent',
            'area_m2' => 1000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        return [$director, $farm, $pondA, $pondB];
    }

    public function test_closed_pond_rejects_new_expense(): void
    {
        [$director, $farm, $pondA] = $this->setupFarm();
        $pondA->update([
            'closed_at' => now(),
            'status' => 'inactive',
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'other',
                'title' => 'Yopilgan havza',
                'expense_date' => '2026-06-02',
                'amount' => 100000,
                'scope' => 'pond',
                'pond_id' => $pondA->id,
            ])
            ->assertStatus(422);
    }

    public function test_expense_can_be_scoped_to_pond_or_farm(): void
    {
        [$director, $farm, $pondA] = $this->setupFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'salary',
                'title' => 'Ish haqi',
                'expense_date' => '2026-06-01',
                'amount' => 1000000,
                'scope' => 'farm',
            ])
            ->assertCreated()
            ->assertJsonPath('pond_id', null);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'other',
                'title' => 'Nasos',
                'expense_date' => '2026-06-02',
                'amount' => 200000,
                'scope' => 'pond',
                'pond_id' => $pondA->id,
            ])
            ->assertCreated()
            ->assertJsonPath('pond_id', $pondA->id);

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(1200000.0, (float) $overview['total_expenses']);
        $this->assertCount(2, $overview['by_pond']);

        $pondOverview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();

        $this->assertSame(200000.0, (float) $pondOverview['total_expenses']);
    }

    public function test_farm_wide_feed_allocates_by_usage(): void
    {
        [$director, $farm, $pondA, $pondB] = $this->setupFarm();

        Expense::create([
            'farm_id' => $farm->id,
            'pond_id' => null,
            'user_id' => $director->id,
            'category' => 'feed',
            'amount' => 1000000,
            'quantity' => 1000,
            'unit_price' => 1000,
            'expense_date' => '2026-05-01',
            'title' => 'Yem',
        ]);

        DailyReport::create([
            'pond_id' => $pondA->id,
            'user_id' => $director->id,
            'report_date' => '2026-05-10',
            'feeding_kg' => 70,
            'wheat_bran_kg' => 0,
        ]);

        DailyReport::create([
            'pond_id' => $pondB->id,
            'user_id' => $director->id,
            'report_date' => '2026-05-10',
            'feeding_kg' => 30,
            'wheat_bran_kg' => 0,
        ]);

        $overviewA = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();

        $overviewB = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondB->id}")
            ->assertOk()
            ->json();

        $this->assertSame(700000.0, (float) $overviewA['total_expenses']);
        $this->assertSame(300000.0, (float) $overviewB['total_expenses']);
        $this->assertSame(700000.0, (float) $overviewA['feed_allocated']);
    }

    public function test_adding_fish_with_price_creates_pond_expense(): void
    {
        [$director, $farm, $pondA] = $this->setupFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pondA->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 1000,
                'average_weight_g' => 50,
                'unit_price' => 20000,
                'operated_at' => '2026-06-01',
                'title' => 'Baliq qo‘shish',
            ])
            ->assertCreated()
            ->assertJsonPath('operation.total_weight_kg', '50.000')
            ->assertJsonPath('operation.amount', '1000000.00');

        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'pond_id' => $pondA->id,
            'category' => 'other',
            'amount' => 1000000,
        ]);

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(1000000.0, (float) $overview['total_expenses']);
    }

    public function test_add_fish_can_derive_average_from_total_mass(): void
    {
        [$director, , $pondA] = $this->setupFarm();

        $payload = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pondA->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 1000,
                'total_weight_kg' => 40,
                'operated_at' => '2026-06-01',
            ])
            ->assertCreated()
            ->json();

        $this->assertEqualsWithDelta(0.04, (float) $payload['operation']['average_weight_kg'], 0.0001);
    }
}
