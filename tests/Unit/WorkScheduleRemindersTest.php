<?php

namespace Tests\Unit;

use App\Models\DailyReport;
use App\Models\Farm;
use App\Models\MainReport;
use App\Models\MainReportFishWeight;
use App\Models\Pond;
use App\Models\User;
use App\Services\WorkScheduleReminders;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkScheduleRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function pondWithStock(?Carbon $createdAt = null): Pond
    {
        $director = User::create([
            'name' => 'D',
            'phone' => '+99890'.random_int(1000000, 9999999),
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(30),
        ]);
        $farm = Farm::create([
            'name' => 'F',
            'region' => 'Sirdaryo',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        $pond = Pond::create([
            'farm_id' => $farm->id,
            'name' => 'Havza 1',
            'region' => 'Sirdaryo',
            'area_m2' => 10,
            'current_stock' => 1000,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);
        $pond->created_at = $createdAt ?? Carbon::parse('2026-06-01', WorkScheduleReminders::TZ);
        $pond->save();

        return $pond->fresh();
    }

    public function test_feed_reminder_after_20_if_no_daily_report(): void
    {
        $pond = $this->pondWithStock();
        $evening = Carbon::parse('2026-08-10 20:15:00', WorkScheduleReminders::TZ);

        $alerts = WorkScheduleReminders::forFarms([$pond->farm_id], $evening);
        $types = collect($alerts)->pluck('type')->all();

        $this->assertContains('feed-reminder', $types);

        DailyReport::create([
            'pond_id' => $pond->id,
            'user_id' => $pond->director_id,
            'report_date' => '2026-08-10',
            'feeding_kg' => 20,
        ]);

        $after = WorkScheduleReminders::forFarms([$pond->farm_id], $evening);
        $this->assertNotContains('feed-reminder', collect($after)->pluck('type')->all());
    }

    public function test_no_feed_reminder_before_20(): void
    {
        $pond = $this->pondWithStock();
        $afternoon = Carbon::parse('2026-08-10 15:00:00', WorkScheduleReminders::TZ);

        $alerts = WorkScheduleReminders::forFarms([$pond->farm_id], $afternoon);
        $this->assertNotContains('feed-reminder', collect($alerts)->pluck('type')->all());
    }

    public function test_water_reminder_when_gap_exceeds_four_days(): void
    {
        $pond = $this->pondWithStock();
        $now = Carbon::parse('2026-08-10 12:00:00', WorkScheduleReminders::TZ);

        MainReport::create([
            'pond_id' => $pond->id,
            'user_id' => $pond->director_id,
            'report_date' => '2026-08-01',
            'ph_level' => 7.2,
            'test_time' => '09:00',
        ]);

        $alerts = WorkScheduleReminders::forFarms([$pond->farm_id], $now);
        $this->assertContains('water-test-reminder', collect($alerts)->pluck('type')->all());
    }

    public function test_weigh_reminder_monthly(): void
    {
        $pond = $this->pondWithStock();
        $now = Carbon::parse('2026-08-10 12:00:00', WorkScheduleReminders::TZ);

        $report = MainReport::create([
            'pond_id' => $pond->id,
            'user_id' => $pond->director_id,
            'report_date' => '2026-06-01',
            'ph_level' => 7.0,
        ]);
        MainReportFishWeight::create([
            'main_report_id' => $report->id,
            'fish_type' => 'sazan',
            'average_weight_kg' => 0.5,
        ]);

        $alerts = WorkScheduleReminders::forFarms([$pond->farm_id], $now);
        $this->assertContains('weigh-reminder', collect($alerts)->pluck('type')->all());
    }
}
