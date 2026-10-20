<?php

namespace Tests\Feature;

use App\Models\FishOperation;
use App\Models\PondClosure;
use App\Models\PondClosureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PondClosureTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(): User
    {
        return User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'role' => User::ROLE_OWNER,
        ]);
    }

    public function test_director_submits_closure_request_instead_of_direct_close(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 500,
                'average_weight_g' => 200,
                'operated_at' => '2026-06-01',
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/close", [
                'mode' => PondClosure::MODE_EARLY,
                'closed_on' => '2026-08-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pond']);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/closure-request", [
                'closed_on' => '2026-08-01',
                'notes' => 'Yakuniy ariza',
                'confirm_all_finished' => true,
                'confirm_no_fish_left' => true,
                'confirm_sales_recorded' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', PondClosureRequest::STATUS_PENDING);

        $this->assertDatabaseHas('pond_closure_requests', [
            'pond_id' => $pond->id,
            'requested_by' => $director->id,
            'status' => PondClosureRequest::STATUS_PENDING,
        ]);

        $request = PondClosureRequest::query()->where('pond_id', $pond->id)->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/closure-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('pond.current_stock', 0);

        $pond->refresh();
        $this->assertNotNull($pond->closed_at);
        $this->assertSame(0, $pond->current_stock);

        $lossQty = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'loss')
            ->sum('quantity');

        $this->assertSame(500, (int) $lossQty);

        $closure = PondClosure::query()->where('pond_id', $pond->id)->firstOrFail();
        $report = $closure->summary_snapshot['closure_final_report'];

        $this->assertSame(500, $report['totals']['stocked']);
        $this->assertSame(0, $report['totals']['sold']);
        $this->assertSame(500, $report['totals']['total_loss']);
        $this->assertSame(500, $report['totals']['closure_loss']);
        $this->assertSame(500, $report['totals']['not_harvested']);
        $this->assertEquals(0.0, $report['totals']['fsr_percent']);
        $this->assertEquals(100.0, $report['totals']['lsr_percent']);
        $this->assertTrue($report['totals']['data_complete']);
    }

    public function test_final_report_calculates_sales_closure_loss_rates_and_revenue(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 500,
                'average_weight_g' => 200,
                'operated_at' => '2026-06-01',
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'sale',
                'fish_type' => 'sazan',
                'quantity' => 400,
                'average_weight_g' => 300,
                'amount' => 4000000,
                'operated_at' => '2026-07-20',
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/closure-request", [
                'closed_on' => '2026-08-01',
                'confirm_all_finished' => true,
                'confirm_no_fish_left' => true,
                'confirm_sales_recorded' => true,
            ])
            ->assertCreated();

        $request = PondClosureRequest::query()->where('pond_id', $pond->id)->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/closure-requests/{$request->id}/approve")
            ->assertOk();

        $closure = PondClosure::query()->where('pond_id', $pond->id)->firstOrFail();
        $report = $closure->summary_snapshot['closure_final_report'];
        $sazan = $report['fish_types'][0];

        $this->assertSame(500, $report['totals']['stocked']);
        $this->assertSame('2026-06-01', $report['started_on']);
        $this->assertSame('2026-08-01', $report['closed_on']);
        $this->assertSame(61, $report['duration_days']);
        $this->assertSame(400, $report['totals']['sold']);
        $this->assertSame(100, $report['totals']['closure_loss']);
        $this->assertSame(100, $report['totals']['total_loss']);
        $this->assertSame(100, $report['totals']['not_harvested']);
        $this->assertEquals(80.0, $report['totals']['fsr_percent']);
        $this->assertEquals(20.0, $report['totals']['lsr_percent']);
        $this->assertEquals(4000000.0, $report['financial']['sales_revenue']);
        $this->assertSame(100, $sazan['closure_loss']);
        $this->assertSame(100, $sazan['loss']);
        $this->assertEquals(80.0, $sazan['fsr_percent']);
        $this->assertEquals(20.0, $sazan['lsr_percent']);
    }

    public function test_owner_approval_skips_loss_when_sales_exceed_added(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 500,
                'average_weight_g' => 200,
                'operated_at' => '2026-06-01',
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'sale',
                'fish_type' => 'sazan',
                'quantity' => 500,
                'average_weight_g' => 300,
                'amount' => 5000000,
                'operated_at' => '2026-07-15',
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/closure-request", [
                'closed_on' => '2026-08-01',
                'confirm_all_finished' => true,
                'confirm_no_fish_left' => true,
                'confirm_sales_recorded' => true,
            ])
            ->assertCreated();

        $request = PondClosureRequest::query()->where('pond_id', $pond->id)->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/closure-requests/{$request->id}/approve")
            ->assertOk();

        $closureLossQty = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'loss')
            ->where('title', 'Havza yopish')
            ->sum('quantity');

        $this->assertSame(0, (int) $closureLossQty);

        $closure = PondClosure::query()->where('pond_id', $pond->id)->first();
        $this->assertNotEmpty($closure->adjustments['loss_free_types']);
    }

    public function test_owner_can_still_close_directly_with_legacy_mode(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 1000,
                'average_weight_g' => 250,
                'operated_at' => '2026-06-01',
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/close", [
                'mode' => PondClosure::MODE_FINAL,
                'closed_on' => '2026-08-10',
                'actual_lines' => [
                    [
                        'fish_type' => 'sazan',
                        'quantity' => 850,
                        'total_weight_kg' => 212.5,
                    ],
                ],
            ])
            ->assertCreated();

        $pond->refresh();
        $this->assertNotNull($pond->closed_at);
        $this->assertSame(0, $pond->current_stock);
    }

    public function test_closed_pond_blocks_new_fish_operations(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/closure-request", [
                'closed_on' => '2026-08-01',
                'confirm_all_finished' => true,
                'confirm_no_fish_left' => true,
                'confirm_sales_recorded' => true,
            ])
            ->assertCreated();

        $request = PondClosureRequest::query()->where('pond_id', $pond->id)->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/closure-requests/{$request->id}/approve")
            ->assertOk();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 10,
                'average_weight_g' => 100,
                'operated_at' => '2026-08-02',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pond']);
    }

    public function test_pending_closure_request_blocks_pond_operations(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 100,
                'average_weight_g' => 200,
                'operated_at' => '2026-06-01',
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/closure-request", [
                'closed_on' => '2026-08-01',
                'confirm_all_finished' => true,
                'confirm_no_fish_left' => true,
                'confirm_sales_recorded' => true,
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'sale',
                'fish_type' => 'sazan',
                'quantity' => 10,
                'average_weight_g' => 200,
                'operated_at' => '2026-08-02',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pond']);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-08-02',
                'feed_name' => 'Granula',
                'feeding_kg' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pond']);
    }

    public function test_rejected_closure_request_unlocks_pond(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/closure-request", [
                'closed_on' => '2026-08-01',
                'confirm_all_finished' => true,
                'confirm_no_fish_left' => true,
                'confirm_sales_recorded' => true,
            ])
            ->assertCreated();

        $request = PondClosureRequest::query()->where('pond_id', $pond->id)->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/closure-requests/{$request->id}/reject")
            ->assertOk();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 50,
                'average_weight_g' => 200,
                'operated_at' => '2026-08-03',
            ])
            ->assertCreated();
    }

    public function test_closure_archive_available_after_close(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/closure-request", [
                'closed_on' => '2026-08-01',
                'confirm_all_finished' => true,
                'confirm_no_fish_left' => true,
                'confirm_sales_recorded' => true,
            ])
            ->assertCreated();

        $request = PondClosureRequest::query()->where('pond_id', $pond->id)->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/closure-requests/{$request->id}/approve")
            ->assertOk();

        $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/ponds/{$pond->id}/closure-archive")
            ->assertOk()
            ->assertJsonStructure(['pond', 'closure', 'archive']);
    }
}
