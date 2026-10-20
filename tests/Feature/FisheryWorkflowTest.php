<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\DailyReport;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FisheryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private Pond $pond;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->director, $this->farm, $this->pond] = $this->createDirectorWithFarmAndPond([
            'director' => [
                'name' => 'Workflow Direktor',
                'email' => null,
                'phone' => '+998901112299',
            ],
            'pond' => [
                'name' => 'Workflow havza',
                'region' => 'Toshkent',
                'area_m2' => 500,
            ],
        ]);
    }

    public function test_full_fishery_workflow_links_stock_reports_and_summary(): void
    {
        $api = fn () => $this->actingAs($this->director, 'sanctum');

        // 1. Baliq qo'shish → zaxira oshadi
        $api()->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
            'type' => 'add',
            'fish_type' => 'sazan',
            'quantity' => 1000,
            'average_weight_g' => 100,
            'operated_at' => '2026-01-01',
        ])->assertCreated()
            ->assertJsonPath('current_stock', 1000);

        // 2. Kunlik hisobot → yem FCR ga kiradi
        $api()->postJson("/api/director/farms/{$this->farm->id}/finance/expenses", [
            'category' => 'feed',
            'title' => 'Granula 35',
            'expense_date' => '2026-02-01',
            'quantity' => 500,
            'unit_price' => 3500,
        ])->assertCreated();

        $api()->postJson("/api/director/farms/{$this->farm->id}/finance/expenses", [
            'category' => 'feed',
            'title' => 'Kepak',
            'expense_date' => '2026-02-01',
            'quantity' => 200,
            'unit_price' => 1500,
        ])->assertCreated();

        $api()->postJson("/api/director/ponds/{$this->pond->id}/daily-reports", [
            'report_date' => '2026-02-01',
            'feed_name' => 'Granula 35',
            'feeding_kg' => 200,
            'wheat_bran_kg' => 50,
        ])->assertCreated();

        // 3. Asosiy hisobot → vazn keyingi hisobga ta'sir qiladi
        $api()->postJson("/api/director/ponds/{$this->pond->id}/main-reports", [
            'report_date' => '2026-04-01',
            'weigh_fish' => true,
            'fish_weights' => [
                ['fish_type' => 'sazan', 'average_weight_g' => 600],
            ],
            'do_mg_l' => 6.5,
        ])->assertCreated();

        $mainReports = $api()->getJson("/api/director/ponds/{$this->pond->id}/main-reports")
            ->assertOk()
            ->json('suggested_weights.sazan.average_weight_g');
        $this->assertEquals(600, $mainReports);

        // 4. Sotish kg orqali → tur bo'yicha zaxira kamayadi
        $api()->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
            'type' => 'sale',
            'fish_type' => 'sazan',
            'total_weight_kg' => 300,
            'average_weight_g' => 600,
            'unit_price' => 10000,
            'operated_at' => '2026-05-01',
        ])->assertCreated()
            ->assertJsonPath('operation.quantity', 500)
            ->assertJsonPath('operation.amount', '3000000.00')
            ->assertJsonPath('current_stock', 500);

        // 5. Summary barcha qismlarni bog'laydi
        $summary = $api()->getJson("/api/director/ponds/{$this->pond->id}/summary")
            ->assertOk()
            ->json();

        $this->assertSame(500, $summary['totals']['fish_count']);
        $this->assertEquals(250.0, $summary['totals']['feed_total_kg']);
        $this->assertSame(500, $summary['sazan']['count']);
        $this->assertEquals(600, $summary['sazan']['average_weight_g']);

        // 6. Fish-stock faqat qolgan turlarni ko'rsatadi
        $stock = $api()->getJson("/api/director/ponds/{$this->pond->id}/fish-stock")
            ->assertOk()
            ->json('stock');
        $this->assertCount(1, $stock);
        $this->assertSame('sazan', $stock[0]['fish_type']);
        $this->assertSame(500, $stock[0]['count']);

        // 7. Faoliyatlar tarixi barcha jarayonlarni qayd etadi
        $activities = $api()->getJson("/api/director/ponds/{$this->pond->id}/activities")
            ->assertOk()
            ->json('data');

        $actions = collect($activities)->pluck('action')->all();
        $this->assertContains('fish_add', $actions);
        $this->assertContains('daily_report', $actions);
        $this->assertContains('main_report', $actions);
        $this->assertContains('fish_sale', $actions);
    }

    public function test_can_sell_without_prior_stock(): void
    {
        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
                'type' => 'sale',
                'fish_type' => 'sazan',
                'total_weight_kg' => 10,
                'average_weight_g' => 500,
                'operated_at' => '2026-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('operation.quantity', 20);

        $this->assertSame(-20, (int) $this->pond->fresh()->current_stock);
        $this->assertSame(1, FishOperation::count());
    }

    public function test_loss_derives_missing_metric_from_any_two(): void
    {
        $api = fn () => $this->actingAs($this->director, 'sanctum');

        $api()->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
            'type' => 'add',
            'fish_type' => 'sazan',
            'quantity' => 1000,
            'average_weight_g' => 200,
            'operated_at' => '2026-01-01',
        ])->assertCreated();

        // son + gramm → kg
        $api()->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
            'type' => 'loss',
            'fish_type' => 'sazan',
            'quantity' => 100,
            'average_weight_g' => 200,
            'operated_at' => '2026-02-01',
        ])->assertCreated()
            ->assertJsonPath('operation.quantity', 100)
            ->assertJsonPath('operation.total_weight_kg', '20.000');

        // son + kg → gramm
        $api()->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
            'type' => 'loss',
            'fish_type' => 'sazan',
            'quantity' => 50,
            'total_weight_kg' => 25,
            'operated_at' => '2026-02-02',
        ])->assertCreated()
            ->assertJsonPath('operation.quantity', 50)
            ->assertJsonPath('operation.average_weight_kg', '0.500')
            ->assertJsonPath('operation.total_weight_kg', '25.000');

        // gramm + kg → son
        $api()->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
            'type' => 'loss',
            'fish_type' => 'sazan',
            'average_weight_g' => 250,
            'total_weight_kg' => 50,
            'operated_at' => '2026-02-03',
        ])->assertCreated()
            ->assertJsonPath('operation.quantity', 200)
            ->assertJsonPath('operation.total_weight_kg', '50.000');

        $this->assertSame(650, (int) $this->pond->fresh()->current_stock);
    }
}
