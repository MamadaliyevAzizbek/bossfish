<?php

namespace Tests\Feature;

use App\Models\OwnerInboxTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPlannerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::create([
            'name' => 'Egasi',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_load_and_save_weekly_planner(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/planner')
            ->assertOk()
            ->assertJsonStructure([
                'content' => [
                    'days' => [
                        'mon' => [
                            'before_bomdod',
                            'after_bomdod',
                        ],
                    ],
                ],
            ]);

        $payload = [
            'content' => [
                'days' => [
                    'mon' => [
                        'before_bomdod' => [
                            ['text' => 'Tayyorgarlik', 'color' => 'mint'],
                        ],
                        'after_bomdod' => [
                            ['text' => 'Havzalar holati', 'color' => 'sky'],
                            ['text' => 'Kechikkan hisobot', 'color' => 'red'],
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/owner/planner', $payload)
            ->assertOk()
            ->assertJsonPath('content.days.mon.before_bomdod.0.text', 'Tayyorgarlik')
            ->assertJsonPath('content.days.mon.after_bomdod.1.color', 'red');
    }

    public function test_owner_can_save_yearly_day_and_see_it_in_daily(): void
    {
        $owner = $this->owner();
        $date = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/owner/planner/yearly', [
                'date' => $date,
                'cells' => [
                    ['text' => 'Yillik vazifa', 'color' => 'sand'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath("content.dates.$date.0.text", 'Yillik vazifa');

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/owner/planner/daily/extras', [
                'date' => $tomorrow,
                'schedule' => [
                    'after_peshin' => [
                        ['text' => 'Ertaga qo‘shimcha', 'color' => 'mint'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('schedule.after_peshin.0.text', 'Ertaga qo‘shimcha');

        $daily = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/planner/daily')
            ->assertOk()
            ->json();

        $this->assertSame($date, $daily['today']['date']);
        $this->assertTrue(collect($daily['today']['from_yearly'])->contains(fn ($i) => $i['text'] === 'Yillik vazifa'));
        $this->assertSame('Ertaga qo‘shimcha', $daily['tomorrow']['schedule']['after_peshin'][0]['text']);
        $this->assertArrayHasKey('before_bomdod', $daily['today']['schedule']);
    }

    public function test_inbox_task_moves_to_completed(): void
    {
        $owner = $this->owner();

        $task = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/planner/inbox', ['text' => 'Muhim ish'])
            ->assertCreated()
            ->json();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/planner/inbox/{$task['id']}/complete")
            ->assertOk()
            ->assertJsonPath('text', 'Muhim ish');

        $this->assertNotNull(OwnerInboxTask::find($task['id'])->completed_at);

        $daily = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/planner/daily')
            ->assertOk()
            ->json();

        $this->assertCount(0, $daily['inbox']);
        $this->assertTrue(collect($daily['completed'])->contains(fn ($i) => $i['text'] === 'Muhim ish'));
    }

    public function test_owner_can_save_monthly_and_see_it_in_daily(): void
    {
        $owner = $this->owner();
        $day = (int) now()->day;

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/owner/planner/monthly', [
                'day' => $day,
                'cells' => [
                    ['text' => 'Oylik vazifa', 'color' => 'ocean'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath("content.days.$day.0.text", 'Oylik vazifa');

        $daily = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/planner/daily')
            ->assertOk()
            ->json();

        $this->assertTrue(collect($daily['today']['from_monthly'])->contains(fn ($i) => $i['text'] === 'Oylik vazifa'));
    }

    public function test_monthly_day_31_spills_to_last_day_of_short_month(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/owner/planner/monthly', [
                'day' => 31,
                'cells' => [
                    ['text' => '31-kun ishi', 'color' => 'sand'],
                ],
            ])
            ->assertOk();

        // Fevral oxiri (28) — 31-kundagi vazifa shu kunda chiqadi
        Carbon::setTestNow(Carbon::create(2026, 2, 28, 12, 0, 0, config('app.timezone')));

        $daily = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/planner/daily')
            ->assertOk()
            ->json();

        $this->assertTrue(collect($daily['today']['from_monthly'])->contains(fn ($i) => $i['text'] === '31-kun ishi'));

        Carbon::setTestNow();
    }

    public function test_owner_can_save_long_term_yearly_plans(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/owner/planner/yearly', [
                'long_term' => [
                    ['id' => 'lt_1', 'text' => 'Yangi fermerlik', 'color' => 'mint', 'completed_at' => null],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('content.long_term.0.text', 'Yangi fermerlik')
            ->assertJsonPath('content.long_term.0.completed_at', null);
    }

    public function test_director_cannot_access_owner_planner(): void
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901234567',
            'password' => 'secret',
            'password_plain' => 'secret',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/owner/planner')
            ->assertForbidden();
    }
}
