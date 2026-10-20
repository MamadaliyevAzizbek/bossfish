<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PondSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private Pond $pond;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->director, , $this->pond] = $this->createDirectorWithBranchAndPond([
            'director' => [
                'name' => 'Test Direktor',
                'email' => null,
                'phone' => '+998901112255',
            ],
        ]);
    }

    private function addFish(string $type, int $qty, float $weight, string $date): void
    {
        FishOperation::create([
            'pond_id' => $this->pond->id,
            'user_id' => $this->director->id,
            'type' => 'add',
            'fish_type' => $type,
            'quantity' => $qty,
            'average_weight_kg' => $weight,
            'operated_at' => $date,
        ]);

        $this->pond->increment('current_stock', $qty);
    }

    private function summary(): array
    {
        return $this->actingAs($this->director, 'sanctum')
            ->getJson("/api/director/ponds/{$this->pond->id}/summary")
            ->assertOk()
            ->json();
    }

    public function test_totals_show_count_and_mass(): void
    {
        $this->addFish('sazan', 1000, 0.100, '2026-01-01');
        $this->addFish('tolstolob', 500, 0.200, '2026-01-01');

        $summary = $this->summary();

        $this->assertSame(1500, $summary['totals']['fish_count']);
        // sazan 1000 * 0.1 = 100, tolstolob 500 * 0.2 = 100
        $this->assertEquals(200.0, $summary['totals']['total_mass_kg']);
    }

    public function test_new_fish_weight_is_entered_in_grams(): void
    {
        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 100,
                'average_weight_g' => 250,
                'operated_at' => '2026-01-01',
            ])
            ->assertCreated();

        $this->assertEquals(0.250, (float) FishOperation::first()->average_weight_kg);
        $this->assertEquals(250, $this->summary()['sazan']['average_weight_g']);
    }

    public function test_fcr_and_daily_growth_use_sazan_and_feed_plus_bran(): void
    {
        $this->addFish('sazan', 1000, 0.100, '2026-01-01');

        // 100 kun keyin sazan vazni 0.600 kg
        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/main-reports", [
                'report_date' => '2026-04-11',
                'weigh_fish' => true,
                'fish_weights' => [
                    ['fish_type' => 'sazan', 'average_weight_g' => 600],
                ],
            ])
            ->assertCreated();

        DailyReport::create([
            'pond_id' => $this->pond->id,
            'user_id' => $this->director->id,
            'report_date' => '2026-02-01',
            'feed_name' => 'Granula 35',
            'feeding_kg' => 400,
            'wheat_bran_kg' => 100,
            'fertilizer_kg' => 20,
        ]);

        $summary = $this->summary();
        $sazan = $summary['sazan'];

        $this->assertEquals(500.0, $summary['totals']['feed_total_kg']);
        $this->assertEquals(20.0, $summary['totals']['fertilizer_kg']);

        // o'sish: 0.6 - 0.1 = 0.5 kg/dona, 1000 dona = 500 kg
        $this->assertEquals(500, $sazan['gain_per_fish_g']);
        $this->assertEquals(500.0, $sazan['biomass_gain_kg']);

        // FCR = 500 kg yem / 500 kg o'sish = 1.0
        $this->assertEquals(1.0, $sazan['fcr']);

        // 2026-01-01 -> 2026-04-11 = 100 kun, 500 g / 100 kun = 5 g/kun
        $this->assertSame(100, $sazan['growth_days']);
        $this->assertEquals(5.0, $sazan['daily_growth_per_fish_g']);
    }

    public function test_fish_growth_board_returns_series_for_each_type(): void
    {
        $this->addFish('sazan', 1000, 0.100, '2026-01-01');
        $this->addFish('tolstolob', 300, 0.150, '2026-01-10');

        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/main-reports", [
                'report_date' => '2026-04-11',
                'weigh_fish' => true,
                'fish_weights' => [
                    ['fish_type' => 'sazan', 'average_weight_g' => 600],
                    ['fish_type' => 'tolstolob', 'average_weight_g' => 400],
                ],
            ])
            ->assertCreated();

        $summary = $this->summary();
        $growth = collect($summary['fish_growth'])->keyBy('fish_type');

        $this->assertArrayHasKey('fish_growth', $summary);
        $this->assertTrue($growth->has('sazan'));
        $this->assertTrue($growth->has('tolstolob'));

        $sazanGrowth = $growth->get('sazan');
        $this->assertTrue($sazanGrowth['has_weighing']);
        $this->assertSame(100, $sazanGrowth['growth_days']);
        $this->assertEquals(500, $sazanGrowth['gain_per_fish_g']);
        $this->assertCount(2, $sazanGrowth['series']);
        $this->assertEquals(100, $sazanGrowth['series'][0]['weight_g']);
        $this->assertEquals(600, $sazanGrowth['series'][1]['weight_g']);

        $tolstolobGrowth = $growth->get('tolstolob');
        $this->assertTrue($tolstolobGrowth['has_weighing']);
        $this->assertEquals(250, $tolstolobGrowth['gain_per_fish_g']);
    }

    public function test_other_fish_listed_separately_without_fcr(): void
    {
        $this->addFish('sazan', 100, 0.100, '2026-01-01');
        $this->addFish('oq_amur', 200, 0.300, '2026-01-01');

        $summary = $this->summary();

        $this->assertNotNull($summary['sazan']);
        $this->assertCount(1, $summary['other_fish']);
        $this->assertSame('oq_amur', $summary['other_fish'][0]['fish_type']);
        $this->assertEquals(300, $summary['other_fish'][0]['average_weight_g']);
        $this->assertEquals(60, $summary['other_fish'][0]['mass_kg']);
        $this->assertArrayNotHasKey('fcr', $summary['other_fish'][0]);
    }

    public function test_sold_fish_reduce_typed_stock_only(): void
    {
        $this->addFish('sazan', 800, 0.500, '2026-01-01');
        $this->addFish('tolstolob', 200, 0.500, '2026-01-01');

        // 400 dona sazan sotildi -> faqat sazan kamayadi
        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
                'type' => 'sale',
                'fish_type' => 'sazan',
                'quantity' => 400,
                'average_weight_g' => 500,
                'amount' => 1000000,
                'operated_at' => '2026-03-01',
            ])
            ->assertCreated();

        $summary = $this->summary();

        $this->assertSame(600, $summary['totals']['fish_count']);
        $this->assertSame(400, $summary['sazan']['count']);
        $this->assertSame(200, $summary['other_fish'][0]['count']);
    }

    public function test_sale_by_total_kg_calculates_quantity(): void
    {
        $this->addFish('sazan', 1000, 0.500, '2026-01-01');

        // 100 kg / 0.5 kg = 200 dona
        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
                'type' => 'sale',
                'fish_type' => 'sazan',
                'total_weight_kg' => 100,
                'average_weight_g' => 500,
                'amount' => 2500000,
                'operated_at' => '2026-03-01',
            ])
            ->assertCreated()
            ->assertJsonPath('operation.quantity', 200)
            ->assertJsonPath('current_stock', 800);

        $this->assertEquals(100.0, (float) FishOperation::where('type', 'sale')->first()->total_weight_kg);
        $this->assertSame(800, $this->summary()['sazan']['count']);
    }

    public function test_can_sell_more_than_typed_stock(): void
    {
        $this->addFish('sazan', 100, 0.500, '2026-01-01');
        $this->addFish('tolstolob', 500, 0.500, '2026-01-01');

        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
                'type' => 'sale',
                'fish_type' => 'sazan',
                'total_weight_kg' => 100,
                'average_weight_g' => 500,
                'operated_at' => '2026-03-01',
            ])
            ->assertCreated()
            ->assertJsonPath('operation.quantity', 200)
            ->assertJsonPath('current_stock', 400);

        $this->assertSame(400, (int) $this->pond->fresh()->current_stock);
        $this->assertSame(0, $this->summary()['sazan']['count']);
    }

    public function test_fish_stock_endpoint_lists_available_types(): void
    {
        $this->addFish('sazan', 300, 0.400, '2026-01-01');
        $this->addFish('oq_amur', 100, 0.600, '2026-01-01');

        $response = $this->actingAs($this->director, 'sanctum')
            ->getJson("/api/director/ponds/{$this->pond->id}/fish-stock")
            ->assertOk()
            ->json('stock');

        $this->assertCount(2, $response);
        $this->assertSame('sazan', $response[0]['fish_type']);
        $this->assertSame(300, $response[0]['count']);
        $this->assertEquals(120.0, $response[0]['mass_kg']);
    }

    public function test_weighted_average_from_multiple_additions(): void
    {
        $this->addFish('sazan', 100, 0.006, '2026-01-01');
        $this->addFish('sazan', 200, 0.003, '2026-01-15');
        $this->addFish('sazan', 100, 0.010, '2026-02-01');

        $summary = $this->summary();

        // (100×6 + 200×3 + 100×10) / 400 = 5.5 g
        $this->assertEquals(400, $summary['sazan']['count']);
        $this->assertEquals(5.5, $summary['sazan']['average_weight_g']);
        $this->assertEquals(5.5, $summary['sazan']['initial_weight_g']);
        $this->assertNull($summary['sazan']['fcr']);
    }

    public function test_fcr_appears_only_after_main_report(): void
    {
        $this->addFish('sazan', 1000, 0.100, '2026-01-01');

        DailyReport::create([
            'pond_id' => $this->pond->id,
            'user_id' => $this->director->id,
            'report_date' => '2026-02-01',
            'feed_name' => 'Granula 35',
            'feeding_kg' => 400,
            'wheat_bran_kg' => 100,
        ]);

        $beforeMain = $this->summary()['sazan'];
        $this->assertNull($beforeMain['fcr']);
        $this->assertNull($beforeMain['gain_per_fish_g']);

        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/director/ponds/{$this->pond->id}/main-reports", [
                'report_date' => '2026-04-11',
                'weigh_fish' => true,
                'fish_weights' => [
                    ['fish_type' => 'sazan', 'average_weight_g' => 600],
                ],
            ])
            ->assertCreated();

        $afterMain = $this->summary()['sazan'];
        $this->assertEquals(100, $afterMain['initial_weight_g']);
        $this->assertEquals(600, $afterMain['average_weight_g']);
        $this->assertEquals(1.0, $afterMain['fcr']);
    }

    public function test_feed_consumption_trend_returns_daily_series_with_deltas(): void
    {
        DailyReport::create([
            'pond_id' => $this->pond->id,
            'user_id' => $this->director->id,
            'report_date' => '2026-02-01',
            'feed_name' => 'Granula 35',
            'feeding_kg' => 40,
            'wheat_bran_kg' => 10,
        ]);

        DailyReport::create([
            'pond_id' => $this->pond->id,
            'user_id' => $this->director->id,
            'report_date' => '2026-02-02',
            'feed_name' => 'Granula 35',
            'feeding_kg' => 55,
            'wheat_bran_kg' => 10,
        ]);

        $summary = $this->summary();
        $trend = $summary['feed_consumption'];

        $this->assertArrayHasKey('feed_consumption', $summary);
        $this->assertCount(2, $trend['series']);
        $this->assertEquals(50.0, $trend['series'][0]['feed_total_kg']);
        $this->assertNull($trend['series'][0]['delta_kg']);
        $this->assertEquals(65.0, $trend['series'][1]['feed_total_kg']);
        $this->assertEquals(15.0, $trend['series'][1]['delta_kg']);
        $this->assertEquals(115.0, $trend['summary']['total_kg']);
        $this->assertEquals(57.5, $trend['summary']['average_daily_kg']);
    }

    public function test_summary_without_fish_returns_null_sazan(): void
    {
        $summary = $this->summary();

        $this->assertNull($summary['sazan']);
        $this->assertSame(0, $summary['totals']['fish_count']);
        $this->assertSame([], $summary['other_fish']);
    }
}
