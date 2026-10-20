<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmInvestment;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithFarm(): array
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901114455',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(300),
        ]);

        $farm = Farm::create([
            'name' => '7 Gektar',
            'region' => 'Sirdaryo',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        $pond = Pond::create([
            'farm_id' => $farm->id,
            'name' => 'Havza 1',
            'region' => 'Sirdaryo',
            'area_m2' => 70000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        return [$director, $farm, $pond];
    }

    public function test_director_can_manage_finance_entries_and_see_overview(): void
    {
        [$director, $farm, $pond] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'other',
                'title' => 'Kol ijarasi',
                'expense_date' => '2026-06-22',
                'quantity' => 1,
                'unit_price' => 174000000,
            ])
            ->assertCreated()
            ->assertJsonPath('amount', '174000000.00');

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'medicine',
                'title' => 'AquaJoy',
                'expense_date' => '2026-06-26',
                'quantity' => 24,
                'unit_price' => 170000,
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/investments", [
                'investor' => 'Hua',
                'amount' => 72000000,
                'invested_at' => '2026-06-01',
                'notes' => 'Changan orqali',
            ])
            ->assertCreated();

        FishOperation::create([
            'pond_id' => $pond->id,
            'user_id' => $director->id,
            'type' => 'sale',
            'fish_type' => 'sazan',
            'quantity' => 1000,
            'average_weight_kg' => 0.2,
            'total_weight_kg' => 200,
            'amount' => 5000000,
            'title' => 'Sotuv',
            'operated_at' => '2026-08-01',
        ]);

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(178080000.0, (float) $overview['total_expenses']);
        $this->assertSame(72000000.0, (float) $overview['total_investments']);
        $this->assertSame(5000000.0, (float) $overview['total_sales']);
        $this->assertSame(-173080000.0, (float) $overview['profit']);

        $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/expenses?category=other")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $expense = Expense::first();
        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/finance/expenses/{$expense->id}")
            ->assertOk();

        $this->assertSame(1, Expense::count());
        $this->assertSame(1, FarmInvestment::count());
    }
}
