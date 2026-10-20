<?php

namespace Tests\Unit;

use App\Services\WaterQualityStandards;
use PHPUnit\Framework\TestCase;

class WaterQualityStandardsTest extends TestCase
{
    public function test_morning_low_do_is_danger_with_morning_action(): void
    {
        $result = WaterQualityStandards::evaluate([
            'test_time' => '06:30',
            'do_mg_l' => 2.0,
        ]);

        $this->assertSame('danger', $result['level']);
        $this->assertSame('morning', $result['band']['key']);
        $this->assertSame('do_mg_l', $result['issues'][0]['metric']);
        $this->assertSame('waterAlertDoDanger', $result['issues'][0]['message_key']);
        $this->assertSame('waterActionDoDangerMorning', $result['issues'][0]['action_key']);
        $this->assertSame('waterActionDoDangerMorning', $result['primary_action_key']);
    }

    public function test_day_borderline_do_is_caution(): void
    {
        $result = WaterQualityStandards::evaluate([
            'test_time' => '12:00',
            'do_mg_l' => 4.0,
        ]);

        $this->assertSame('caution', $result['level']);
        $this->assertSame('waterActionDoCaution', $result['primary_action_key']);
    }

    public function test_kit_based_nitrite_top_range_is_danger(): void
    {
        $result = WaterQualityStandards::evaluate([
            'test_time' => '12:00',
            'nitrite_mg_l' => 0.25,
        ]);

        $this->assertSame('danger', $result['level']);
        $this->assertSame('nitrite_mg_l', $result['issues'][0]['metric']);
        $this->assertSame('waterActionNitriteDanger', $result['issues'][0]['action_key']);
    }

    public function test_evening_ammonia_with_high_ph_is_stricter(): void
    {
        $okMorning = WaterQualityStandards::evaluate([
            'test_time' => '07:00',
            'ph_level' => 7.2,
            'ammonia_mg_l' => 0.4,
        ]);
        $this->assertSame('ok', $okMorning['level']);
        $this->assertSame('waterActionOk', $okMorning['primary_action_key']);

        $eveningHighPh = WaterQualityStandards::evaluate([
            'test_time' => '18:00',
            'ph_level' => 8.7,
            'ammonia_mg_l' => 0.4,
        ]);
        $this->assertNotSame('ok', $eveningHighPh['level']);

        $ammoniaIssue = $this->firstIssue($eveningHighPh['issues'], 'ammonia_mg_l');
        $this->assertNotNull($ammoniaIssue);
        $this->assertTrue($ammoniaIssue['message_params']['high_ph']);
        $this->assertStringContainsString('HighPh', $ammoniaIssue['action_key']);
    }

    public function test_evening_ammonia_without_high_ph_uses_evening_action(): void
    {
        $result = WaterQualityStandards::evaluate([
            'test_time' => '18:30',
            'ph_level' => 7.5,
            'ammonia_mg_l' => 0.45,
        ]);

        $this->assertSame('caution', $result['level']);
        $ammonia = $this->firstIssue($result['issues'], 'ammonia_mg_l');
        $this->assertSame('waterActionAmmoniaEveningCaution', $ammonia['action_key']);
    }

    /**
     * @param  list<array{metric: string}>  $issues
     * @return array<string, mixed>|null
     */
    private function firstIssue(array $issues, string $metric): ?array
    {
        foreach ($issues as $issue) {
            if (($issue['metric'] ?? null) === $metric) {
                return $issue;
            }
        }

        return null;
    }

    public function test_ph_low_and_high_get_different_actions(): void
    {
        $low = WaterQualityStandards::evaluate([
            'test_time' => '11:00',
            'ph_level' => 6.0,
        ]);
        $this->assertSame('danger', $low['level']);
        $this->assertSame('waterActionPhLowDanger', $low['primary_action_key']);

        $high = WaterQualityStandards::evaluate([
            'test_time' => '11:00',
            'ph_level' => 9.3,
        ]);
        $this->assertSame('danger', $high['level']);
        $this->assertSame('waterActionPhHighDanger', $high['primary_action_key']);
    }

    public function test_transparency_low_and_high_diagnoses(): void
    {
        $low = WaterQualityStandards::evaluate([
            'test_time' => '14:00',
            'water_transparency_cm' => 10,
        ]);
        $this->assertSame('danger', $low['level']);
        $this->assertSame('waterAlertTransparencyLowDanger', $low['issues'][0]['message_key']);
        $this->assertSame('waterActionTransparencyLowDanger', $low['primary_action_key']);

        $high = WaterQualityStandards::evaluate([
            'test_time' => '14:00',
            'water_transparency_cm' => 75,
        ]);
        $this->assertSame('danger', $high['level']);
        $this->assertSame('waterAlertTransparencyHighDanger', $high['issues'][0]['message_key']);
        $this->assertSame('waterActionTransparencyHighDanger', $high['primary_action_key']);
    }

    public function test_normal_kit_values_pass(): void
    {
        $result = WaterQualityStandards::evaluate([
            'test_time' => '09:00',
            'do_mg_l' => 5.5,
            'ph_level' => 7.4,
            'ammonia_mg_l' => 0.2,
            'nitrite_mg_l' => 0.02,
            'water_transparency_cm' => 30,
        ]);

        $this->assertSame('ok', $result['level']);
        $this->assertCount(0, $result['issues']);
        $this->assertSame('waterActionOk', $result['primary_action_key']);
    }

    public function test_missing_time_with_tests_is_caution(): void
    {
        $result = WaterQualityStandards::evaluate([
            'ph_level' => 7.5,
        ]);

        $this->assertSame('caution', $result['level']);
        $this->assertSame('test_time', $result['issues'][0]['metric']);
        $this->assertSame('waterActionMissingTime', $result['primary_action_key']);
    }

    public function test_danger_outranks_caution_for_primary_action(): void
    {
        $result = WaterQualityStandards::evaluate([
            'test_time' => '07:00',
            'do_mg_l' => 2.0,
            'ammonia_mg_l' => 0.7,
        ]);

        $this->assertSame('danger', $result['level']);
        $this->assertSame('waterActionDoDangerMorning', $result['primary_action_key']);
        $this->assertGreaterThanOrEqual(2, count($result['issues']));
        foreach ($result['issues'] as $issue) {
            $this->assertNotEmpty($issue['message_key']);
            $this->assertNotEmpty($issue['action_key']);
        }
    }

    public function test_time_bands_boundaries(): void
    {
        $this->assertSame('morning', WaterQualityStandards::timeBand('05:00')['key']);
        $this->assertSame('day', WaterQualityStandards::timeBand('10:00')['key']);
        $this->assertSame('evening', WaterQualityStandards::timeBand('16:00')['key']);
        $this->assertSame('night', WaterQualityStandards::timeBand('21:00')['key']);
        $this->assertSame('night', WaterQualityStandards::timeBand('03:00')['key']);
        $this->assertSame('unknown', WaterQualityStandards::timeBand(null)['key']);
    }

    public function test_exact_ok_boundary_values_pass(): void
    {
        $result = WaterQualityStandards::evaluate([
            'test_time' => '06:00',
            'do_mg_l' => 4.0,
            'ph_level' => 6.5,
            'ammonia_mg_l' => 0.5,
            'nitrite_mg_l' => 0.05,
            'water_transparency_cm' => 25,
        ]);

        $this->assertSame('ok', $result['level']);
    }
}
