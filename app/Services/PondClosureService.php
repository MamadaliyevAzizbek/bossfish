<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\FishOperation;
use App\Models\MainReport;
use App\Models\Pond;
use App\Models\PondClosure;
use App\Models\PondClosureRequest;
use App\Models\User;
use App\Support\FishTypes;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PondClosureService
{
    public function buildAnalysis(Pond $pond): array
    {
        $labels = FishTypes::labels();
        $weights = $this->suggestedFishWeights($pond);
        $lines = [];
        $overallLossFree = true;

        foreach ($this->stockAnalysis($pond) as $type => $row) {
            if (($row['added'] ?? 0) <= 0 && ($row['remaining'] ?? 0) <= 0) {
                continue;
            }

            $added = (int) ($row['added'] ?? 0);
            $sold = (int) ($row['sold'] ?? 0);
            $loss = (int) ($row['loss'] ?? 0);
            $remaining = max(0, (int) ($row['remaining'] ?? 0));
            $saleRatio = $added > 0 ? round(($sold / $added) * 100, 1) : null;
            $lossFree = $added > 0 && $sold >= $added;

            if ($remaining > 0 && ! $lossFree) {
                $overallLossFree = false;
            }

            $averageWeightKg = $weights[$type]['average_weight_kg'] ?? null;

            $lines[] = [
                'fish_type' => $type,
                'label' => $labels[$type] ?? $type,
                'added' => $added,
                'sold' => $sold,
                'loss' => $loss,
                'remaining' => $remaining,
                'sale_ratio_percent' => $saleRatio,
                'loss_free' => $lossFree,
                'average_weight_kg' => $averageWeightKg,
                'remaining_mass_kg' => $averageWeightKg !== null ? round($remaining * $averageWeightKg, 3) : null,
            ];
        }

        return [
            'fish_types' => $lines,
            'overall_loss_free' => $overallLossFree,
            'total_remaining' => array_sum(array_column($lines, 'remaining')),
        ];
    }

    public function buildEstimatedStockSnapshot(Pond $pond): Collection
    {
        $labels = FishTypes::labels();
        $weights = $this->suggestedFishWeights($pond);
        $stockByType = $this->stockAnalysis($pond);

        return collect(FishTypes::all())
            ->map(function (string $type) use ($labels, $weights, $stockByType) {
                $count = max(0, (int) ($stockByType[$type]['remaining'] ?? 0));
                $averageWeightKg = $weights[$type]['average_weight_kg'] ?? null;

                return [
                    'fish_type' => $type,
                    'label' => $labels[$type] ?? $type,
                    'count' => $count,
                    'average_weight_kg' => $averageWeightKg,
                    'mass_kg' => $averageWeightKg !== null ? round($count * $averageWeightKg, 3) : null,
                ];
            })
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();
    }

    public function executeApprovedClosure(
        Pond $pond,
        User $executor,
        string $closedOn,
        ?string $notes,
        array $summarySnapshot,
        ?PondClosureRequest $request = null,
    ): PondClosure {
        $estimated = $this->buildEstimatedStockSnapshot($pond);
        $analysis = $this->buildAnalysis($pond);

        $adjustments = [
            'losses' => [],
            'surplus' => [],
            'loss_free_types' => [],
        ];

        $closure = DB::transaction(function () use (
            $pond,
            $executor,
            $closedOn,
            $notes,
            $summarySnapshot,
            $estimated,
            $analysis,
            &$adjustments,
        ) {
            $pond = Pond::whereKey($pond->id)->lockForUpdate()->first();
            $weights = $this->suggestedFishWeights($pond);
            $closedOnDate = Carbon::parse($closedOn)->toDateString();

            foreach ($analysis['fish_types'] as $line) {
                $fishType = $line['fish_type'];
                $remaining = (int) ($line['remaining'] ?? 0);

                if (! empty($line['loss_free'])) {
                    $adjustments['loss_free_types'][] = [
                        'fish_type' => $fishType,
                        'added' => $line['added'],
                        'sold' => $line['sold'],
                        'remaining' => $remaining,
                        'reason' => 'sales_exceed_added',
                    ];
                }

                if ($remaining <= 0) {
                    continue;
                }

                if (! empty($line['loss_free'])) {
                    continue;
                }

                $averageWeightKg = ($line['average_weight_kg'] ?? null)
                    ?: ($weights[$fishType]['average_weight_kg'] ?? null)
                    ?: 0.001;

                $operation = $this->recordClosureLoss(
                    $pond,
                    $executor->id,
                    $fishType,
                    $remaining,
                    (float) $averageWeightKg,
                    $closedOnDate,
                    'Havza yopish — qoldiq baliq yo‘qotish deb qayd etildi'
                );

                if ($operation) {
                    $adjustments['losses'][] = [
                        'fish_type' => $fishType,
                        'quantity' => $remaining,
                        'operation_id' => $operation->id,
                        'reason' => 'remaining_at_closure',
                        'added' => $line['added'],
                        'sold' => $line['sold'],
                        'loss' => $line['loss'],
                    ];
                }
            }

            $pond->refresh();
            $remainingLines = $this->buildEstimatedStockSnapshot($pond);
            foreach ($remainingLines as $line) {
                $qty = (int) ($line['count'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $averageWeightKg = ($line['average_weight_kg'] ?? null) ?: 0.001;
                $operation = $this->recordClosureLoss(
                    $pond,
                    $executor->id,
                    $line['fish_type'],
                    $qty,
                    (float) $averageWeightKg,
                    $closedOnDate,
                    'Havza yopish — qoldiq tozalash'
                );

                if ($operation) {
                    $adjustments['losses'][] = [
                        'fish_type' => $line['fish_type'],
                        'quantity' => $qty,
                        'operation_id' => $operation->id,
                        'reason' => 'cleanup',
                    ];
                }
            }

            $finalReport = $this->buildFinalReport(
                $pond,
                $summarySnapshot,
                $adjustments,
                $closedOnDate
            );

            $pond->current_stock = 0;
            $pond->closed_at = Carbon::parse($closedOnDate)->endOfDay();
            $pond->status = 'inactive';
            $pond->save();

            return PondClosure::create([
                'pond_id' => $pond->id,
                'user_id' => $executor->id,
                'mode' => PondClosure::MODE_APPROVED,
                'closed_on' => $closedOnDate,
                'estimated_stock' => $estimated->values()->all(),
                'actual_stock' => null,
                'adjustments' => $adjustments,
                'summary_snapshot' => array_merge($summarySnapshot, [
                    'closure_analysis' => [
                        'fish_types' => $finalReport['fish_types'],
                        'overall_loss_free' => $finalReport['totals']['total_loss'] === 0,
                        'total_remaining' => 0,
                    ],
                    'closure_final_report' => $finalReport,
                ]),
                'notes' => $notes,
            ]);
        });

        $this->logActivity(
            $pond,
            $executor->id,
            'pond_closed',
            'Havza yopildi',
            $pond->name.' havzasi BOSS tasdiqlashi bilan yopildi',
            [
                'mode' => $closure->mode,
                'closed_on' => $closure->closed_on?->toDateString(),
                'request_id' => $request?->id,
            ],
            $closure->closed_on
        );

        return $closure;
    }

    private function buildFinalReport(
        Pond $pond,
        array $summarySnapshot,
        array $adjustments,
        string $closedOn
    ): array {
        $labels = FishTypes::labels();
        $operations = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->whereDate('operated_at', '<=', $closedOn)
            ->get();
        $startedOn = optional(
            $operations
                ->where('type', 'add')
                ->sortBy('operated_at')
                ->first()
        )->operated_at?->toDateString();
        $durationDays = $startedOn
            ? Carbon::parse($startedOn)->diffInDays(Carbon::parse($closedOn))
            : null;

        $closureLossByType = collect($adjustments['losses'] ?? [])
            ->groupBy('fish_type')
            ->map(fn (Collection $rows) => (int) $rows->sum('quantity'));

        $fishTypes = [];
        foreach (FishTypes::all() as $type) {
            $typed = $operations->where('fish_type', $type);
            $added = (int) $typed->where('type', 'add')->sum('quantity');
            $sold = (int) $typed->where('type', 'sale')->sum('quantity');
            $totalLoss = (int) $typed->where('type', 'loss')->sum('quantity');
            $closureLoss = (int) ($closureLossByType[$type] ?? 0);

            if ($added <= 0 && $sold <= 0 && $totalLoss <= 0) {
                continue;
            }

            $notHarvested = max(0, $added - $sold);
            $unaccounted = max(0, $added - $sold - $totalLoss);
            $fsr = $added > 0 ? round(($sold / $added) * 100, 1) : null;
            $lsr = $added > 0 ? round(($totalLoss / $added) * 100, 1) : null;

            $fishTypes[] = [
                'fish_type' => $type,
                'label' => $labels[$type] ?? $type,
                'added' => $added,
                'sold' => $sold,
                'loss' => $totalLoss,
                'loss_before_closure' => max(0, $totalLoss - $closureLoss),
                'closure_loss' => $closureLoss,
                'remaining' => 0,
                'not_harvested' => $notHarvested,
                'unaccounted' => $unaccounted,
                'fsr_percent' => $fsr,
                'lsr_percent' => $lsr,
                'sale_ratio_percent' => $fsr,
                'loss_free' => $totalLoss === 0 && $sold >= $added,
                'sold_mass_kg' => round((float) $typed->where('type', 'sale')->sum('total_weight_kg'), 3),
                'loss_mass_kg' => round((float) $typed->where('type', 'loss')->sum('total_weight_kg'), 3),
            ];
        }

        $totalAdded = (int) collect($fishTypes)->sum('added');
        $totalSold = (int) collect($fishTypes)->sum('sold');
        $totalLoss = (int) collect($fishTypes)->sum('loss');
        $closureLoss = (int) collect($fishTypes)->sum('closure_loss');
        $notHarvested = max(0, $totalAdded - $totalSold);
        $unaccounted = max(0, $totalAdded - $totalSold - $totalLoss);

        $addedMassKg = round((float) $operations->where('type', 'add')->sum('total_weight_kg'), 3);
        $soldMassKg = round((float) $operations->where('type', 'sale')->sum('total_weight_kg'), 3);
        $lossMassKg = round((float) $operations->where('type', 'loss')->sum('total_weight_kg'), 3);
        $salesRevenue = round((float) $operations->where('type', 'sale')->sum('amount'), 2);
        $averageSalePriceKg = ($soldMassKg > 0 && $salesRevenue > 0)
            ? round($salesRevenue / $soldMassKg, 2)
            : null;
        $estimatedLossValue = $averageSalePriceKg !== null
            ? round($lossMassKg * $averageSalePriceKg, 2)
            : null;

        $feedKg = round((float) $pond->dailyReports()
            ->whereDate('report_date', '<=', $closedOn)
            ->sum('feeding_kg'), 2);
        $branKg = round((float) $pond->dailyReports()
            ->whereDate('report_date', '<=', $closedOn)
            ->sum('wheat_bran_kg'), 2);
        $feedTotalKg = round($feedKg + $branKg, 2);
        $producedBiomassGainKg = round(($soldMassKg + $lossMassKg) - $addedMassKg, 3);
        $fcr = $producedBiomassGainKg > 0
            ? round($feedTotalKg / $producedBiomassGainKg, 2)
            : null;

        return [
            'version' => 1,
            'started_on' => $startedOn,
            'closed_on' => $closedOn,
            'duration_days' => $durationDays,
            'fish_types' => $fishTypes,
            'totals' => [
                'stocked' => $totalAdded,
                'sold' => $totalSold,
                'total_loss' => $totalLoss,
                'closure_loss' => $closureLoss,
                'not_harvested' => $notHarvested,
                'unaccounted' => $unaccounted,
                'fsr_percent' => $totalAdded > 0 ? round(($totalSold / $totalAdded) * 100, 1) : null,
                'lsr_percent' => $totalAdded > 0 ? round(($totalLoss / $totalAdded) * 100, 1) : null,
                'data_complete' => $unaccounted === 0,
            ],
            'mass' => [
                'stocked_kg' => $addedMassKg,
                'sold_kg' => $soldMassKg,
                'loss_kg' => $lossMassKg,
                'biomass_gain_kg' => $producedBiomassGainKg > 0 ? $producedBiomassGainKg : null,
            ],
            'feed' => [
                'feed_kg' => $feedKg,
                'bran_kg' => $branKg,
                'total_kg' => $feedTotalKg,
                'fcr' => $fcr,
            ],
            'financial' => [
                'sales_revenue' => $salesRevenue,
                'average_sale_price_per_kg' => $averageSalePriceKg,
                'estimated_loss_value' => $estimatedLossValue,
                'result_before_expenses' => $estimatedLossValue !== null
                    ? round($salesRevenue - $estimatedLossValue, 2)
                    : null,
                'expenses_included' => false,
            ],
            'source_summary' => [
                'fertilizer_kg' => data_get($summarySnapshot, 'totals.fertilizer_kg'),
            ],
        ];
    }

    /** @return array<string, array{added: int, sold: int, loss: int, remaining: int}> */
    private function stockAnalysis(Pond $pond): array
    {
        $addedByType = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'add')
            ->whereNotNull('fish_type')
            ->select('fish_type', DB::raw('SUM(quantity) as qty'))
            ->groupBy('fish_type')
            ->pluck('qty', 'fish_type');

        $soldByType = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'sale')
            ->whereNotNull('fish_type')
            ->select('fish_type', DB::raw('SUM(quantity) as qty'))
            ->groupBy('fish_type')
            ->pluck('qty', 'fish_type');

        $lossByType = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'loss')
            ->whereNotNull('fish_type')
            ->select('fish_type', DB::raw('SUM(quantity) as qty'))
            ->groupBy('fish_type')
            ->pluck('qty', 'fish_type');

        $result = [];

        foreach (FishTypes::all() as $type) {
            $added = (int) ($addedByType[$type] ?? 0);
            $sold = (int) ($soldByType[$type] ?? 0);
            $loss = (int) ($lossByType[$type] ?? 0);
            $remaining = max(0, $added - $sold - $loss);

            $result[$type] = [
                'added' => $added,
                'sold' => $sold,
                'loss' => $loss,
                'remaining' => $remaining,
            ];
        }

        return $result;
    }

    private function suggestedFishWeights(Pond $pond): array
    {
        $suggestions = [];

        foreach (FishTypes::all() as $type) {
            $fromMain = MainReport::query()
                ->where('pond_id', $pond->id)
                ->whereHas('fishWeights', fn ($q) => $q->where('fish_type', $type))
                ->with(['fishWeights' => fn ($q) => $q->where('fish_type', $type)])
                ->orderByDesc('report_date')
                ->first();

            if ($fromMain && $fromMain->fishWeights->isNotEmpty()) {
                $weight = $fromMain->fishWeights->first();
                $avgKg = $weight->average_weight_kg ? (float) $weight->average_weight_kg : null;
                if ($avgKg) {
                    $suggestions[$type] = [
                        'average_weight_kg' => $avgKg,
                        'source' => 'main_report',
                        'source_date' => $fromMain->report_date?->toDateString(),
                    ];

                    continue;
                }
            }

            $adds = FishOperation::query()
                ->where('pond_id', $pond->id)
                ->where('type', 'add')
                ->where('fish_type', $type)
                ->whereNotNull('average_weight_kg')
                ->get(['quantity', 'average_weight_kg']);

            if ($adds->isNotEmpty()) {
                $totalQty = $adds->sum('quantity');
                $weighted = $totalQty > 0
                    ? $adds->sum(fn ($op) => $op->quantity * (float) $op->average_weight_kg) / $totalQty
                    : null;

                if ($weighted) {
                    $suggestions[$type] = [
                        'average_weight_kg' => round($weighted, 4),
                        'source' => 'additions',
                    ];
                }
            }
        }

        return $suggestions;
    }

    private function recordClosureLoss(
        Pond $pond,
        int $userId,
        string $fishType,
        int $quantity,
        float $averageWeightKg,
        string $operatedAt,
        string $notes
    ): ?FishOperation {
        if ($quantity <= 0) {
            return null;
        }

        $totalWeightKg = round($quantity * $averageWeightKg, 3);

        if ($pond->current_stock < $quantity) {
            $quantity = max(0, (int) $pond->current_stock);
            $totalWeightKg = round($quantity * $averageWeightKg, 3);
        }

        if ($quantity <= 0) {
            return null;
        }

        $pond->current_stock -= $quantity;
        $pond->save();

        $operation = FishOperation::create([
            'pond_id' => $pond->id,
            'user_id' => $userId,
            'type' => 'loss',
            'fish_type' => $fishType,
            'quantity' => $quantity,
            'average_weight_kg' => $averageWeightKg,
            'total_weight_kg' => $totalWeightKg,
            'amount' => null,
            'title' => 'Havza yopish',
            'notes' => $notes,
            'operated_at' => $operatedAt,
        ]);

        $this->logActivity(
            $pond,
            $userId,
            'fish_loss',
            'Baliq poteryasi qayd etildi',
            $operation->quantity.' dona '.$fishType.' (havza yopish)',
            [
                'fish_type' => $fishType,
                'quantity' => $operation->quantity,
                'total_weight_kg' => $operation->total_weight_kg,
                'closure' => true,
            ],
            $operatedAt
        );

        return $operation;
    }

    private function logActivity(
        Pond $pond,
        int $userId,
        string $action,
        string $title,
        ?string $description = null,
        array $details = [],
        mixed $performedAt = null
    ): void {
        ActivityLog::create([
            'pond_id' => $pond->id,
            'user_id' => $userId,
            'action' => $action,
            'title' => $title,
            'description' => $description,
            'details' => $details,
            'performed_at' => $performedAt ?? now(),
        ]);
    }
}
