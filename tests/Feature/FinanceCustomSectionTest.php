<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FinanceSection;
use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceCustomSectionTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithFarm(): array
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901117788',
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

    public function test_director_can_create_custom_section_and_add_expense(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $section = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/sections", [
                'name' => 'Elektr energiya',
            ])
            ->assertCreated()
            ->json();

        $category = $section['category'];

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => $category,
                'title' => 'Avgust oyi',
                'expense_date' => '2026-08-01',
                'amount' => 1500000,
            ])
            ->assertCreated();

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertCount(1, $overview['custom_sections']);
        $this->assertSame(1500000.0, (float) $overview['custom_sections'][0]['total']);
        $this->assertDatabaseCount('warehouse_items', 0);
    }

    public function test_custom_section_expense_does_not_sync_to_warehouse(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $section = FinanceSection::create([
            'farm_id' => $farm->id,
            'name' => 'Soliq',
            'created_by' => $director->id,
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => $section->categoryKey(),
                'title' => 'QQS',
                'expense_date' => '2026-08-01',
                'quantity' => 100,
                'unit_price' => 1000,
            ])
            ->assertCreated();

        $this->assertSame(0, WarehouseItem::count());
    }

    public function test_cannot_delete_custom_section_with_expenses(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $section = FinanceSection::create([
            'farm_id' => $farm->id,
            'name' => 'Transport',
            'created_by' => $director->id,
        ]);

        Expense::create([
            'farm_id' => $farm->id,
            'user_id' => $director->id,
            'category' => $section->categoryKey(),
            'title' => 'Yoqilg\'i',
            'amount' => 500000,
            'expense_date' => '2026-08-01',
        ]);

        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/finance/sections/{$section->id}")
            ->assertStatus(422);
    }
}
