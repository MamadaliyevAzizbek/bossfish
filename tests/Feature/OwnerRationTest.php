<?php

namespace Tests\Feature;

use App\Models\FeedProduct;
use App\Models\FeedRation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerRationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Egasi',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->director = User::create([
            'name' => 'Direktor',
            'phone' => '+998901112266',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_manage_products_and_rations(): void
    {
        $product = FeedProduct::create([
            'name' => 'Bugdoy',
            'price_per_kg' => 5000,
            'category' => FeedProduct::CATEGORY_FEED,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/rations/products')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Bugdoy']);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/rations', [
                'name' => 'Svitina',
                'kop_narx' => 200,
                'items' => [
                    ['feed_product_id' => $product->id, 'qty_per_100kg' => 60],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('ration.name', 'Svitina')
            ->assertJsonPath('ration.totals.qty_per_100kg', 60)
            ->assertJsonPath('ration.totals.total_sum', 300000)
            ->assertJsonPath('ration.totals.cost_per_kg', 5000)
            ->assertJsonPath('ration.totals.cost_with_kop', 5200)
            ->assertJsonPath('ration.variant_400kg.batch_kg', 400)
            ->assertJsonPath('ration.variant_400kg.items.0.qty_kg', 240);
    }

    public function test_product_price_change_recalculates_ration_totals(): void
    {
        $product = FeedProduct::create([
            'name' => 'Makaron',
            'price_per_kg' => 1000,
            'category' => FeedProduct::CATEGORY_FEED,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/rations', [
                'name' => 'Test ratsion',
                'items' => [
                    ['feed_product_id' => $product->id, 'qty_per_100kg' => 10],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('ration.totals.total_sum', 10000);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/rations/products/{$product->id}", [
                'price_per_kg' => 2000,
            ])
            ->assertOk();

        $rationId = FeedRation::query()->value('id');

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/owner/rations/{$rationId}")
            ->assertOk()
            ->assertJsonPath('ration.totals.total_sum', 20000);
    }

    public function test_sale_price_calculates_profit_and_margin(): void
    {
        $product = FeedProduct::create([
            'name' => 'Kepak',
            'price_per_kg' => 3520,
            'category' => FeedProduct::CATEGORY_FEED,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/rations', [
                'name' => 'Start',
                'kop_narx' => 52.5,
                'items' => [
                    ['feed_product_id' => $product->id, 'qty_per_100kg' => 100],
                ],
            ])
            ->assertCreated();

        $rationId = $response->json('ration.id');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/rations/{$rationId}/sale-price", [
                'sale_price_per_kg' => 7600,
            ])
            ->assertOk()
            ->assertJsonPath('ration.sale_price_per_kg', 7600)
            ->assertJsonPath('ration.totals.cost_with_kop', 3572.5)
            ->assertJsonPath('ration.totals.profit_per_kg', 4027.5)
            ->assertJsonPath('ration.totals.margin_percent', 52.99);
    }

    public function test_director_cannot_access_owner_ration_routes(): void
    {
        $this->actingAs($this->director, 'sanctum')
            ->getJson('/api/owner/rations')
            ->assertForbidden();
    }

    public function test_cannot_delete_product_used_in_ration(): void
    {
        $product = FeedProduct::create([
            'name' => 'Yem',
            'price_per_kg' => 3000,
            'category' => FeedProduct::CATEGORY_FEED,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/rations', [
                'name' => 'Ratsion',
                'items' => [
                    ['feed_product_id' => $product->id, 'qty_per_100kg' => 5],
                ],
            ])
            ->assertCreated();

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/owner/rations/products/{$product->id}")
            ->assertStatus(422);
    }
}
