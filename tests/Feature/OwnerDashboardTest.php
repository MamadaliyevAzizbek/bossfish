<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_counts_and_total_area(): void
    {
        $owner = User::create([
            'name' => 'Egasi',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Direktor 1',
            'phone' => '+998901110001',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Direktor 2',
            'phone' => '+998901110002',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Hodim',
            'phone' => '+998901110003',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_WORKER,
            'is_active' => true,
        ]);

        Pond::create([
            'name' => 'Havza 1',
            'region' => 'Toshkent',
            'area_m2' => 1200,
            'current_stock' => 5000,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        Pond::create([
            'name' => 'Havza 2',
            'region' => 'Samarqand',
            'area_m2' => 800.5,
            'current_stock' => 3000,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/dashboard')
            ->assertOk()
            ->assertJsonPath('directors_count', 2)
            ->assertJsonPath('workers_count', 1)
            ->assertJsonPath('users_count', 3)
            ->assertJsonPath('farms_count', 0)
            ->assertJsonPath('ponds_count', 2)
            ->assertJsonPath('total_area_ha', 2000.5)
            ->assertJsonPath('total_fish_count', 8000)
            ->assertJsonCount(2, 'by_region');
    }

    public function test_directors_list_is_alphabetical_and_filterable_by_region(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $zafar = User::create([
            'name' => 'Zafar',
            'phone' => '+998901110010',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $ali = User::create([
            'name' => 'Ali',
            'phone' => '+998901110011',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        Pond::create([
            'name' => 'Tosh havza',
            'region' => 'Toshkent',
            'area_m2' => 500,
            'director_id' => $zafar->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        Pond::create([
            'name' => 'Tosh 2',
            'region' => 'Toshkent',
            'area_m2' => 300,
            'director_id' => $zafar->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        Pond::create([
            'name' => 'Samar havza',
            'region' => 'Samarqand',
            'area_m2' => 400,
            'director_id' => $ali->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/directors')
            ->assertOk()
            ->assertJsonPath('0.name', 'Ali')
            ->assertJsonPath('1.name', 'Zafar')
            ->assertJsonPath('0.regions.0', 'Samarqand')
            ->assertJsonPath('1.regions.0', 'Toshkent')
            ->assertJsonPath('0.ponds_total_area_m2', 400)
            ->assertJsonPath('1.ponds_total_area_m2', 800)
            ->assertJsonPath('0.directed_ponds_count', 1)
            ->assertJsonPath('1.directed_ponds_count', 2);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/directors?region=Toshkent')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Zafar');
    }

    public function test_directors_list_includes_profit_and_loss_totals(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss-finance@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $profitDirector = User::create([
            'name' => 'Foydali',
            'phone' => '+998901110020',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $lossDirector = User::create([
            'name' => 'Zararli',
            'phone' => '+998901110021',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $profitFarm = Farm::create([
            'name' => 'Foyda xo‘jalik',
            'region' => 'Toshkent',
            'director_id' => $profitDirector->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $lossFarm = Farm::create([
            'name' => 'Zarar xo‘jalik',
            'region' => 'Samarqand',
            'director_id' => $lossDirector->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $profitPond = Pond::create([
            'farm_id' => $profitFarm->id,
            'name' => 'Foyda havza',
            'region' => 'Toshkent',
            'area_m2' => 100,
            'director_id' => $profitDirector->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $lossPond = Pond::create([
            'farm_id' => $lossFarm->id,
            'name' => 'Zarar havza',
            'region' => 'Samarqand',
            'area_m2' => 100,
            'director_id' => $lossDirector->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        Expense::create([
            'farm_id' => $profitFarm->id,
            'user_id' => $profitDirector->id,
            'category' => 'other',
            'amount' => 100000,
            'expense_date' => '2026-06-01',
            'title' => 'Kichik xarajat',
        ]);

        FishOperation::create([
            'pond_id' => $profitPond->id,
            'user_id' => $profitDirector->id,
            'type' => 'sale',
            'fish_type' => 'sazan',
            'quantity' => 10,
            'average_weight_kg' => 1,
            'total_weight_kg' => 10,
            'amount' => 400000,
            'title' => 'Sotuv',
            'operated_at' => '2026-07-01',
        ]);

        Expense::create([
            'farm_id' => $lossFarm->id,
            'user_id' => $lossDirector->id,
            'category' => 'feed',
            'amount' => 500000,
            'expense_date' => '2026-06-01',
            'title' => 'Yem',
        ]);

        FishOperation::create([
            'pond_id' => $lossPond->id,
            'user_id' => $lossDirector->id,
            'type' => 'sale',
            'fish_type' => 'sazan',
            'quantity' => 5,
            'average_weight_kg' => 1,
            'total_weight_kg' => 5,
            'amount' => 100000,
            'title' => 'Sotuv',
            'operated_at' => '2026-07-01',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/directors')
            ->assertOk()
            ->assertJsonPath('0.name', 'Foydali')
            ->assertJsonPath('0.total_sales', 400000)
            ->assertJsonPath('0.total_expenses', 100000)
            ->assertJsonPath('0.profit', 300000)
            ->assertJsonPath('1.name', 'Zararli')
            ->assertJsonPath('1.total_sales', 100000)
            ->assertJsonPath('1.total_expenses', 500000)
            ->assertJsonPath('1.profit', -400000);
    }
}
