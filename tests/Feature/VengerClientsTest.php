<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VengerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VengerClientsTest extends TestCase
{
    use RefreshDatabase;

    private function vengerUser(): User
    {
        return User::create([
            'name' => 'Venger Staff',
            'email' => 'hodim@vengercarp.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'role' => User::ROLE_VENGER,
            'is_active' => true,
        ]);
    }

    public function test_venger_staff_can_create_client_and_result(): void
    {
        $staff = $this->vengerUser();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/venger/clients', [
                'first_name' => 'Akmal',
                'last_name' => 'Karimov',
                'region' => 'Andijon',
                'district' => 'Andijon shahri',
                'address' => 'Mahalla 12',
                'phone' => '+998901112233',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Akmal Karimov')
            ->assertJsonPath('region', 'Andijon')
            ->assertJsonPath('district', 'Andijon shahri');

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/venger/clients', [
                'first_name' => 'Ali',
                'last_name' => 'Valiyev',
                'phone' => '+998901112244',
            ])
            ->assertUnprocessable();

        $client = VengerClient::query()->first();
        $this->assertNotNull($client);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/venger/clients/{$client->id}/results", [
                'area_ha' => 2.5,
                'feeds' => [
                    ['type' => 'extruder', 'kg' => 3200],
                ],
                'notes' => 'Yaxshi o‘sish',
                'fish' => [
                    [
                        'fish_type' => 'sazan',
                        'stocked_on' => '2026-03-01',
                        'stocked_pieces' => 8000,
                        'stocked_weight_kg' => 400,
                        'sold_on' => '2026-08-15',
                        'sold_pieces' => 7200,
                        'sold_weight_kg' => 3200,
                        'loss_on' => '2026-08-10',
                        'loss_pieces' => 800,
                        'loss_weight_kg' => 200,
                    ],
                    [
                        'fish_type' => 'tolstolob',
                        'stocked_on' => '2026-03-05',
                        'stocked_pieces' => 2000,
                        'stocked_weight_kg' => 100,
                        'sold_on' => '2026-08-20',
                        'sold_pieces' => 1800,
                        'sold_weight_kg' => 700,
                        'loss_pieces' => 0,
                        'loss_weight_kg' => 0,
                    ],
                ],
            ])
            ->assertCreated()
            // (3200+200+700) - (400+100) = 3600 — umumiy massa
            ->assertJsonPath('metrics.weight_gain_kg', 3600)
            // FCR faqat sazan: feed 3200 / (3200+200-400) = 3200/3000 = 1.07
            ->assertJsonPath('metrics.fcr', 1.07)
            ->assertJsonPath('metrics.fcr_basis', 'final')
            ->assertJsonPath('metrics.phase', 'final')
            ->assertJsonPath('metrics.sazan.biomass_gain_kg', 3000)
            ->assertJsonPath('status', 'final')
            ->assertJsonPath('feed_kg', 3200)
            ->assertJsonCount(1, 'feeds')
            ->assertJsonPath('feeds.0.type', 'extruder')
            ->assertJsonPath('metrics.loss_pieces', 800)
            ->assertJsonCount(2, 'fish')
            ->assertJsonStructure(['metrics' => ['fcr', 'fsr_percent', 'lsr_percent', 'sazan', 'fish']]);

        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/venger/clients')
            ->assertOk()
            ->assertJsonPath('stats.clients_count', 1);
    }

    public function test_ponds_list_separates_open_and_confirmed(): void
    {
        $staff = $this->vengerUser();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/venger/clients', [
                'first_name' => 'Sarvar',
                'last_name' => 'Aliyev',
                'region' => 'Andijon',
                'district' => 'Andijon shahri',
                'phone' => '+998901112266',
            ])
            ->assertCreated();

        $client = VengerClient::query()->first();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/venger/clients/{$client->id}/results", [
                'area_ha' => 1,
                'status' => 'active',
                'fish' => [[
                    'fish_type' => 'sazan',
                    'stocked_on' => '2026-03-01',
                    'stocked_pieces' => 1000,
                    'stocked_weight_kg' => 50,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'active');

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/venger/ponds')
            ->assertOk()
            ->assertJsonPath('stats.open', 1)
            ->assertJsonPath('stats.final', 0)
            ->assertJsonCount(1, 'open')
            ->assertJsonCount(0, 'final')
            ->assertJsonPath('open.0.workflow', 'started');
    }

    public function test_control_cards_drive_mid_season_fcr(): void
    {
        $staff = $this->vengerUser();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/venger/clients', [
                'first_name' => 'Jasur',
                'last_name' => 'Bek',
                'region' => 'Andijon',
                'district' => 'Andijon shahri',
                'phone' => '+998901112277',
            ])
            ->assertCreated();

        $client = VengerClient::query()->first();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/venger/clients/{$client->id}/results", [
                'area_ha' => 2,
                'status' => 'active',
                'feeds' => [
                    ['type' => 'extruder', 'kg' => 1200],
                ],
                'controls' => [
                    ['checked_on' => '2026-05-01', 'avg_g' => 400],
                    ['checked_on' => '2026-06-01', 'avg_g' => 700],
                ],
                'fish' => [[
                    'fish_type' => 'sazan',
                    'stocked_on' => '2026-03-01',
                    'stocked_pieces' => 2000,
                    'stocked_weight_kg' => 200,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('metrics.phase', 'mid')
            ->assertJsonCount(2, 'controls')
            ->assertJsonPath('feed_kg', 1200)
            // taxminiy biomassa = 2000 * 0.7 = 1400; gain = 1200; FCR = 1200/1200 = 1
            ->assertJsonPath('metrics.sazan.end_weight_kg', 1400)
            ->assertJsonPath('metrics.fcr', 1);
    }

    public function test_mid_season_result_uses_sazan_current_weight_for_fcr(): void
    {
        $staff = $this->vengerUser();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/venger/clients', [
                'first_name' => 'Dilshod',
                'last_name' => 'Nurmatov',
                'region' => 'Andijon',
                'district' => 'Andijon shahri',
                'phone' => '+998901112255',
            ])
            ->assertCreated();

        $client = VengerClient::query()->first();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/venger/clients/{$client->id}/results", [
                'area_ha' => 1.5,
                'feed_kg' => 900,
                'status' => 'active',
                'fish' => [
                    [
                        'fish_type' => 'sazan',
                        'stocked_on' => '2026-03-01',
                        'stocked_pieces' => 5000,
                        'stocked_weight_kg' => 250,
                        'current_on' => '2026-06-01',
                        'current_pieces' => 4800,
                        'current_weight_kg' => 1100,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('metrics.phase', 'mid')
            ->assertJsonPath('metrics.fcr_basis', 'mid')
            // FCR = 900 / (1100 - 250) = 900/850 ≈ 1.06
            ->assertJsonPath('metrics.fcr', 1.06)
            ->assertJsonPath('metrics.sazan.biomass_gain_kg', 850);
    }

    public function test_sale_feed_overrides_control_feed_for_final_fcr(): void
    {
        $staff = $this->vengerUser();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/venger/clients', [
                'first_name' => 'Sotuv',
                'last_name' => 'Yem',
                'region' => 'Andijon',
                'district' => 'Andijon shahri',
                'phone' => '+998901112288',
            ])
            ->assertCreated();

        $client = VengerClient::query()->first();

        $create = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/venger/clients/{$client->id}/results", [
                'area_ha' => 2,
                'status' => 'active',
                'feeds' => [
                    ['type' => 'extruder', 'kg' => 7000],
                ],
                'controls' => [
                    ['checked_on' => '2026-06-01', 'avg_g' => 800],
                ],
                'fish' => [[
                    'fish_type' => 'sazan',
                    'stocked_on' => '2026-03-01',
                    'stocked_pieces' => 10000,
                    'stocked_weight_kg' => 2000,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('metrics.phase', 'mid')
            ->assertJsonPath('metrics.fcr', 1.17);

        $resultId = $create->json('id');

        // Yangi yem 5000 — eski 7000 o‘rniga; FCR yangi miqdor asosida
        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/venger/results/{$resultId}", [
                'area_ha' => 2,
                'status' => 'final',
                'feeds' => [
                    ['type' => 'extruder', 'kg' => 3000],
                    ['type' => 'granula', 'kg' => 2000],
                ],
                'controls' => [
                    ['checked_on' => '2026-06-01', 'avg_g' => 800],
                ],
                'fish' => [[
                    'fish_type' => 'sazan',
                    'stocked_on' => '2026-03-01',
                    'stocked_pieces' => 10000,
                    'stocked_weight_kg' => 2000,
                    'sold_on' => '2026-08-01',
                    'sold_pieces' => 9500,
                    'sold_weight_kg' => 8000,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'final')
            ->assertJsonPath('feed_kg', 5000)
            ->assertJsonCount(2, 'feeds')
            ->assertJsonPath('feeds.0.type', 'extruder')
            ->assertJsonPath('feeds.0.kg', 3000)
            ->assertJsonPath('feeds.1.type', 'granula')
            ->assertJsonPath('feeds.1.kg', 2000)
            ->assertJsonPath('metrics.phase', 'final')
            ->assertJsonPath('metrics.fcr_basis', 'final')
            // gain = 8000 - 2000 = 6000; FCR = 5000/6000 ≈ 0.83 (umumiy yem)
            ->assertJsonPath('metrics.fcr', 0.83)
            ->assertJsonPath('metrics.sazan.biomass_gain_kg', 6000);
    }
}
