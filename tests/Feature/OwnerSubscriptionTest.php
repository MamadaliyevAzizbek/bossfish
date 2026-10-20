<?php

namespace Tests\Feature;

use App\Models\ClientSubscription;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerSubscriptionTest extends TestCase
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

    private function director(): User
    {
        $director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901110099',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        Farm::create([
            'name' => 'Hojalik 1',
            'region' => 'Toshkent',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        return $director->fresh();
    }

    public function test_owner_can_assign_control_plan_and_mark_checked(): void
    {
        $owner = $this->owner();
        $director = $this->director();
        Sanctum::actingAs($owner);

        $create = $this->postJson('/api/owner/subscriptions', [
            'director_id' => $director->id,
            'plan' => ClientSubscription::PLAN_CONTROL,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'sync_access' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('subscription.plan', 'control')
            ->assertJsonPath('subscription.price_amount', 4299000);

        $this->assertNotNull($director->fresh()->access_expires_at);

        $id = $create->json('subscription.id');

        $check = $this->postJson("/api/owner/subscriptions/{$id}/check");
        $check->assertOk();
        $this->assertNotNull($check->json('subscription.last_checked_at'));
        $this->assertNotNull($check->json('subscription.next_check_at'));

        $list = $this->getJson('/api/owner/subscriptions');
        $list->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.control', 1);
    }

    public function test_cabinet_plan_has_no_weekly_check(): void
    {
        $owner = $this->owner();
        $director = $this->director();
        Sanctum::actingAs($owner);

        $create = $this->postJson('/api/owner/subscriptions', [
            'director_id' => $director->id,
            'plan' => ClientSubscription::PLAN_CABINET,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
        ]);

        $create->assertCreated()
            ->assertJsonPath('subscription.next_check_at', null);

        $id = $create->json('subscription.id');
        $this->postJson("/api/owner/subscriptions/{$id}/check")
            ->assertStatus(422);
    }

    public function test_monthly_plan_sets_access_and_price(): void
    {
        $owner = $this->owner();
        $director = $this->director();
        Sanctum::actingAs($owner);

        $starts = now()->toDateString();
        $create = $this->postJson('/api/owner/subscriptions', [
            'director_id' => $director->id,
            'plan' => ClientSubscription::PLAN_CABINET,
            'billing_period' => ClientSubscription::PERIOD_MONTH,
            'starts_at' => $starts,
        ]);

        $expectedEnd = \Illuminate\Support\Carbon::parse($starts)->addDays(30)->toDateString();

        $create->assertCreated()
            ->assertJsonPath('subscription.billing_period', 'month')
            ->assertJsonPath('subscription.price_amount', 135000)
            ->assertJsonPath('subscription.ends_at', $expectedEnd);

        $access = $director->fresh()->access_expires_at;
        $this->assertNotNull($access);
        $this->assertTrue($access->isSameDay(\Illuminate\Support\Carbon::parse($expectedEnd)));
    }

    public function test_directors_expiring_filter_returns_near_end_clients(): void
    {
        $owner = $this->owner();
        $near = $this->director();
        $far = User::create([
            'name' => 'Uzoq',
            'phone' => '+998901110088',
            'password' => '1234',
            'password_plain' => '1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(200),
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/owner/subscriptions', [
            'director_id' => $near->id,
            'plan' => ClientSubscription::PLAN_CABINET,
            'billing_period' => ClientSubscription::PERIOD_MONTH,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(7)->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/owner/subscriptions', [
            'director_id' => $far->id,
            'plan' => ClientSubscription::PLAN_CABINET,
            'billing_period' => ClientSubscription::PERIOD_YEAR,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(200)->toDateString(),
        ])->assertCreated();

        $list = $this->getJson('/api/owner/directors?expiring=1');
        $list->assertOk();
        $ids = collect($list->json())->pluck('id')->all();
        $this->assertContains($near->id, $ids);
        $this->assertNotContains($far->id, $ids);
    }
}
