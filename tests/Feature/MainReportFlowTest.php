<?php

namespace Tests\Feature;

use App\Models\FishOperation;
use App\Models\MainReport;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithPond(): array
    {
        return array_slice($this->createDirectorWithBranchAndPond([
            'pond' => ['current_stock' => 100],
        ]), 0, 2);
    }

    public function test_main_report_saves_tests_and_optional_weights(): void
    {
        [$director, $pond] = $this->directorWithPond();

        $response = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/main-reports", [
                'report_date' => '2026-07-29',
                'test_time' => '09:30',
                'do_mg_l' => 6.5,
                'ph_level' => 7.2,
                'ammonia_mg_l' => 0.12,
                'nitrite_mg_l' => 0.04,
                'water_transparency_cm' => 35,
                'weigh_fish' => true,
                'fish_weights' => [
                    ['fish_type' => 'sazan', 'average_weight_g' => 850],
                    ['fish_type' => 'tolstolob', 'average_weight_g' => 1200],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('water_quality.level', 'ok');

        $report = MainReport::first();
        $this->assertNotNull($report);
        $this->assertSame('2026-07-29', $report->report_date->format('Y-m-d'));
        $this->assertEquals(6.5, (float) $report->do_mg_l);
        $this->assertEquals(7.2, (float) $report->ph_level);
        $this->assertCount(2, $report->fishWeights);
    }

    public function test_out_of_norm_water_test_returns_danger_assessment(): void
    {
        [$director, $pond] = $this->directorWithPond();

        $response = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/main-reports", [
                'report_date' => '2026-07-30',
                'test_time' => '06:30',
                'do_mg_l' => 1.8,
                'ph_level' => 9.3,
                'ammonia_mg_l' => 2.5,
                'nitrite_mg_l' => 0.25,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('water_quality.level', 'danger');
        $this->assertNotEmpty($response->json('water_quality.issues'));
    }

    public function test_suggested_weights_prefer_previous_main_report_then_stock_add(): void
    {
        [$director, $pond] = $this->directorWithPond();

        FishOperation::create([
            'pond_id' => $pond->id,
            'user_id' => $director->id,
            'type' => 'add',
            'fish_type' => 'oq_amur',
            'quantity' => 50,
            'average_weight_kg' => 0.45,
            'operated_at' => '2026-07-20',
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/main-reports", [
                'report_date' => '2026-07-25',
                'weigh_fish' => true,
                'fish_weights' => [
                    ['fish_type' => 'sazan', 'average_weight_g' => 900],
                ],
            ])
            ->assertCreated();

        $response = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/ponds/{$pond->id}/main-reports");

        $response->assertOk();
        $response->assertJsonPath('suggested_weights.sazan.average_weight_kg', 0.9);
        $response->assertJsonPath('suggested_weights.sazan.average_weight_g', 900);
        $response->assertJsonPath('suggested_weights.sazan.source', 'main_report');
        $response->assertJsonPath('suggested_weights.oq_amur.average_weight_kg', 0.45);
        $response->assertJsonPath('suggested_weights.oq_amur.average_weight_g', 450);
        $response->assertJsonPath('suggested_weights.oq_amur.source', 'stock_add');
    }

    public function test_weigh_fish_requires_at_least_one_weight(): void
    {
        [$director, $pond] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/main-reports", [
                'report_date' => '2026-07-29',
                'weigh_fish' => true,
                'fish_weights' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fish_weights']);
    }
}
