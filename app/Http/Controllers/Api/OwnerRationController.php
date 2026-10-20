<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedProduct;
use App\Models\FeedRation;
use App\Models\FeedRationItem;
use App\Services\RationCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerRationController extends Controller
{
    public function __construct(
        private readonly RationCalculator $calculator,
    ) {}

    public function products(): JsonResponse
    {
        $products = FeedProduct::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['products' => $products]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:feed_products,name',
            'price_per_kg' => 'required|numeric|min:0',
            'category' => ['nullable', Rule::in([FeedProduct::CATEGORY_FEED, FeedProduct::CATEGORY_VITAMIN])],
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $product = FeedProduct::create([
            'name' => trim($data['name']),
            'price_per_kg' => $data['price_per_kg'],
            'category' => $data['category'] ?? FeedProduct::CATEGORY_FEED,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['product' => $product], 201);
    }

    public function updateProduct(Request $request, FeedProduct $feedProduct): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('feed_products', 'name')->ignore($feedProduct->id)],
            'price_per_kg' => 'sometimes|numeric|min:0',
            'category' => ['sometimes', Rule::in([FeedProduct::CATEGORY_FEED, FeedProduct::CATEGORY_VITAMIN])],
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        $feedProduct->update($data);

        return response()->json(['product' => $feedProduct->fresh()]);
    }

    public function destroyProduct(FeedProduct $feedProduct): JsonResponse
    {
        if ($feedProduct->rationItems()->exists()) {
            throw ValidationException::withMessages([
                'product' => ['Bu mahsulot ratsionlarda ishlatilgan. Avval ratsiondan olib tashlang.'],
            ]);
        }

        $feedProduct->delete();

        return response()->json(['message' => 'Mahsulot o\'chirildi']);
    }

    public function rations(): JsonResponse
    {
        $rations = FeedRation::query()
            ->with(['items.product'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (FeedRation $ration) => $this->calculator->buildRationPayload($ration));

        return response()->json(['rations' => $rations]);
    }

    public function showRation(FeedRation $feedRation): JsonResponse
    {
        return response()->json([
            'ration' => $this->calculator->buildRationPayload($feedRation),
        ]);
    }

    public function storeRation(Request $request): JsonResponse
    {
        $data = $this->validateRation($request);

        $ration = DB::transaction(function () use ($data) {
            $ration = FeedRation::create([
                'name' => trim($data['name']),
                'kop_narx' => $data['kop_narx'] ?? null,
                'sale_price_per_kg' => $data['sale_price_per_kg'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncItems($ration, $data['items']);

            return $ration;
        });

        return response()->json([
            'ration' => $this->calculator->buildRationPayload($ration->fresh(['items.product'])),
        ], 201);
    }

    public function updateRation(Request $request, FeedRation $feedRation): JsonResponse
    {
        $data = $this->validateRation($request, $feedRation->id);

        DB::transaction(function () use ($feedRation, $data) {
            $feedRation->update([
                'name' => trim($data['name']),
                'kop_narx' => $data['kop_narx'] ?? null,
                'sale_price_per_kg' => $data['sale_price_per_kg'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sort_order' => $data['sort_order'] ?? $feedRation->sort_order,
            ]);

            $this->syncItems($feedRation, $data['items']);
        });

        return response()->json([
            'ration' => $this->calculator->buildRationPayload($feedRation->fresh(['items.product'])),
        ]);
    }

    public function destroyRation(FeedRation $feedRation): JsonResponse
    {
        $feedRation->delete();

        return response()->json(['message' => 'Ratsion o\'chirildi']);
    }

    public function updateSalePrice(Request $request, FeedRation $feedRation): JsonResponse
    {
        $data = $request->validate([
            'sale_price_per_kg' => 'nullable|numeric|min:0',
        ]);

        $feedRation->update([
            'sale_price_per_kg' => $data['sale_price_per_kg'] ?? null,
        ]);

        return response()->json([
            'ration' => $this->calculator->buildRationPayload($feedRation->fresh(['items.product'])),
        ]);
    }

    private function validateRation(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('feed_rations', 'name')->ignore($ignoreId),
            ],
            'kop_narx' => 'nullable|numeric|min:0',
            'sale_price_per_kg' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.feed_product_id' => 'required|exists:feed_products,id',
            'items.*.qty_per_100kg' => 'required|numeric|min:0.001',
        ]);

        $productIds = collect($data['items'])->pluck('feed_product_id');
        if ($productIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Bir xil mahsulot ikki marta kiritilgan.'],
            ]);
        }

        return $data;
    }

    private function syncItems(FeedRation $ration, array $items): void
    {
        $ration->items()->delete();

        foreach ($items as $row) {
            $qty = round((float) $row['qty_per_100kg'], 3);
            if ($qty <= 0) {
                continue;
            }

            FeedRationItem::create([
                'feed_ration_id' => $ration->id,
                'feed_product_id' => $row['feed_product_id'],
                'qty_per_100kg' => $qty,
            ]);
        }
    }
}
