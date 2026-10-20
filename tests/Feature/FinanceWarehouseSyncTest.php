<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWarehouseSyncTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithFarm(): array
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901115566',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
        ]);

        $farm = Farm::create([
            'name' => '7 Gektar',
            'region' => 'Sirdaryo',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        Pond::create([
            'farm_id' => $farm->id,
            'name' => 'Havza 1',
            'region' => 'Sirdaryo',
            'area_m2' => 70000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        return [$director, $farm];
    }

    public function test_feed_expense_adds_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'feed',
                'title' => 'Granula 35',
                'expense_date' => '2026-08-24',
                'quantity' => 500,
                'unit_price' => 3500,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'category' => 'feed',
            'name' => 'Granula 35',
            'quantity' => 500,
        ]);

        $this->assertDatabaseHas('feed_types', [
            'farm_id' => $farm->id,
            'name' => 'Granula 35',
        ]);
    }

    public function test_medicine_expense_adds_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'medicine',
                'title' => 'Antibiotik X',
                'expense_date' => '2026-08-24',
                'quantity' => 20,
                'unit_price' => 50000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'category' => 'medicine',
            'name' => 'Antibiotik X',
            'quantity' => 20,
        ]);
    }

    public function test_other_expense_does_not_add_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'other',
                'title' => 'Kol ijarasi',
                'expense_date' => '2026-08-24',
                'quantity' => 1,
                'unit_price' => 1000000,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('warehouse_items', 0);
    }

    public function test_deleting_feed_expense_removes_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $response = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'feed',
                'title' => 'Granula 35',
                'expense_date' => '2026-08-24',
                'quantity' => 100,
                'unit_price' => 3500,
            ])
            ->assertCreated();

        $expenseId = $response->json('id');

        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/finance/expenses/{$expenseId}")
            ->assertOk();

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'name' => 'Granula 35',
            'quantity' => 0,
        ]);
    }

    public function test_consumable_warehouse_intake_is_blocked(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/intake", [
                'feed_name' => 'Granula 35',
                'feed_kg' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intake']);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'feed',
                'name' => 'Granula 35',
                'unit' => 'kg',
                'quantity' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_equipment_can_be_issued_from_warehouse(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $item = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'equipment',
                'name' => 'Skuter',
                'unit' => 'piece',
                'quantity' => 2,
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/{$item['id']}/issue", [
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('quantity', '1.00');
    }

    public function test_feed_expense_quantity_is_required(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'feed',
                'title' => 'Granula 35',
                'expense_date' => '2026-08-24',
                'amount' => 1000000,
            ])
            ->assertStatus(422);
    }
}
