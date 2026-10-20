<?php

namespace App\Services;

use App\Models\FeedRation;
use Illuminate\Support\Collection;

class RationCalculator
{
    public const BATCH_100_KG = 100;

    public const BATCH_400_KG = 400;

    public function buildRationPayload(FeedRation $ration): array
    {
        $ration->loadMissing(['items.product']);

        $items = $ration->items
            ->filter(fn ($item) => $item->product !== null && (float) $item->qty_per_100kg > 0)
            ->values();

        $lines100 = $items->map(function ($item) {
            $qty = round((float) $item->qty_per_100kg, 3);
            $price = round((float) $item->product->price_per_kg, 2);

            return [
                'product_id' => $item->feed_product_id,
                'product_name' => $item->product->name,
                'category' => $item->product->category,
                'qty_per_100kg' => $qty,
                'price_per_kg' => $price,
                'line_sum' => round($qty * $price, 2),
            ];
        });

        $totalQty = round($lines100->sum('qty_per_100kg'), 3);
        $totalSum = round($lines100->sum('line_sum'), 2);
        $costPerKg = $totalQty > 0 ? round($totalSum / $totalQty, 2) : 0;
        $kopNarx = $ration->kop_narx !== null ? round((float) $ration->kop_narx, 2) : null;
        $costWithKop = $kopNarx !== null ? round($costPerKg + $kopNarx, 2) : null;
        $salePrice = $ration->sale_price_per_kg !== null ? round((float) $ration->sale_price_per_kg, 2) : null;
        $costBase = $costWithKop ?? $costPerKg;
        $profitPerKg = ($salePrice !== null && $costBase > 0) ? round($salePrice - $costBase, 2) : null;
        $marginPercent = ($profitPerKg !== null && $salePrice > 0)
            ? round(($profitPerKg / $salePrice) * 100, 2)
            : null;

        $variant400 = $this->buildBatchVariant($lines100, self::BATCH_400_KG);

        return [
            'id' => $ration->id,
            'name' => $ration->name,
            'kop_narx' => $kopNarx,
            'sale_price_per_kg' => $salePrice,
            'notes' => $ration->notes,
            'sort_order' => $ration->sort_order,
            'items' => $lines100->values()->all(),
            'totals' => [
                'qty_per_100kg' => $totalQty,
                'total_sum' => $totalSum,
                'cost_per_kg' => $costPerKg,
                'cost_with_kop' => $costWithKop,
                'profit_per_kg' => $profitPerKg,
                'margin_percent' => $marginPercent,
            ],
            'variant_400kg' => $variant400,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $lines100 */
    public function buildBatchVariant(Collection $lines100, float $batchKg): array
    {
        $factor = $batchKg / self::BATCH_100_KG;

        $lines = $lines100->map(function (array $line) use ($factor) {
            $qtyKg = round($line['qty_per_100kg'] * $factor, 3);

            return [
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'qty_kg' => $qtyKg,
                'qty_label' => $this->formatKgLabel($qtyKg),
            ];
        })->values();

        return [
            'batch_kg' => $batchKg,
            'items' => $lines->all(),
            'total_kg' => round($lines->sum('qty_kg'), 3),
        ];
    }

    public function formatKgLabel(float $kg): string
    {
        $whole = (int) floor($kg);
        $grams = (int) round(($kg - $whole) * 1000);

        if ($grams <= 0) {
            return $whole.' kg';
        }

        if ($grams >= 1000) {
            return round($kg, 3).' kg';
        }

        return $whole.' kg '.$grams.' gr';
    }
}
