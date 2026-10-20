<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class VengerResult extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'venger_client_id',
        'created_by',
        'area_ha',
        'stocked_on',
        'stocked_pieces',
        'stocked_weight_kg',
        'feed_kg',
        'feeds',
        'controls',
        'sold_on',
        'sold_pieces',
        'sold_weight_kg',
        'title',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area_ha' => 'float',
            'stocked_on' => 'date',
            'stocked_pieces' => 'integer',
            'stocked_weight_kg' => 'float',
            'feed_kg' => 'float',
            'feeds' => 'array',
            'controls' => 'array',
            'sold_on' => 'date',
            'sold_pieces' => 'integer',
            'sold_weight_kg' => 'float',
        ];
    }

    /** @return Collection<int, array{checked_on: string, avg_g: float, feed_kg: float}> */
    public function controlsList(): Collection
    {
        return collect($this->controls ?? [])
            ->filter(fn ($row) => ! empty($row['checked_on']) && (float) ($row['avg_g'] ?? 0) > 0)
            ->map(fn ($row) => [
                'checked_on' => (string) $row['checked_on'],
                'avg_g' => round((float) $row['avg_g'], 1),
                'feed_kg' => round((float) ($row['feed_kg'] ?? 0), 2),
            ])
            ->sortBy('checked_on')
            ->values();
    }

    public function latestControl(): ?array
    {
        return $this->controlsList()->last();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(VengerClient::class, 'venger_client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fish(): HasMany
    {
        return $this->hasMany(VengerResultFish::class)->orderBy('fish_type');
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function syncAggregatesFromFish(?Collection $rows = null): void
    {
        $rows = $rows ?? $this->fish()->get();

        $stockedOn = $rows->min('stocked_on');
        $soldOn = $rows->pluck('sold_on')->filter()->max();

        $this->forceFill([
            'stocked_on' => $stockedOn,
            'sold_on' => $soldOn ?: $stockedOn,
            'stocked_pieces' => (int) $rows->sum('stocked_pieces'),
            'stocked_weight_kg' => round((float) $rows->sum('stocked_weight_kg'), 2),
            'sold_pieces' => (int) $rows->sum('sold_pieces'),
            'sold_weight_kg' => round((float) $rows->sum('sold_weight_kg'), 2),
        ])->save();
    }

    public function metrics(): array
    {
        $fishRows = $this->relationLoaded('fish') ? $this->fish : $this->fish()->get();
        $fishMetrics = $fishRows->map(fn (VengerResultFish $row) => [
            ...$row->only([
                'id',
                'fish_type',
                'stocked_on',
                'stocked_pieces',
                'stocked_weight_kg',
                'current_on',
                'current_pieces',
                'current_weight_kg',
                'sold_on',
                'sold_pieces',
                'sold_weight_kg',
                'loss_on',
                'loss_pieces',
                'loss_weight_kg',
                'notes',
            ]),
            'stocked_on' => optional($row->stocked_on)?->toDateString(),
            'current_on' => optional($row->current_on)?->toDateString(),
            'sold_on' => optional($row->sold_on)?->toDateString(),
            'loss_on' => optional($row->loss_on)?->toDateString(),
            'metrics' => $row->metrics(),
        ])->values()->all();

        $stockedWeight = (float) $fishRows->sum('stocked_weight_kg');
        $soldWeight = (float) $fishRows->sum('sold_weight_kg');
        $lossWeight = (float) $fishRows->sum('loss_weight_kg');
        $stockedPieces = (int) $fishRows->sum('stocked_pieces');
        $soldPieces = (int) $fishRows->sum('sold_pieces');
        $lossPieces = (int) $fishRows->sum('loss_pieces');
        $controls = $this->controlsList();
        $latestControl = $controls->last();
        $sazan = $fishRows->firstWhere('fish_type', 'sazan');
        $phase = 'stocked';
        if ($this->isFinal() || ($sazan && (float) $sazan->sold_weight_kg > 0)) {
            $phase = 'final';
        } elseif ($latestControl || ($sazan && (float) ($sazan->current_weight_kg ?? 0) > 0)) {
            $phase = 'mid';
        }

        // Yem faqat natijadagi umumiy feed_kg (yuqoridagi Ekstruder/Granula)
        $feedKg = (float) $this->feed_kg;
        $areaHa = max((float) $this->area_ha, 0.0001);

        $stockedOn = $fishRows->min('stocked_on') ?: $this->stocked_on;
        $endOn = $fishRows->pluck('sold_on')->filter()->max()
            ?: ($phase === 'mid' ? ($latestControl['checked_on'] ?? null) : null)
            ?: ($fishRows->pluck('current_on')->filter()->max()
                ?: ($fishRows->pluck('loss_on')->filter()->max() ?: ($this->sold_on ?: $stockedOn)));

        $durationDays = max(
            (int) Carbon::parse($stockedOn)->diffInDays(Carbon::parse($endOn)) + 1,
            1
        );

        // Umumiy massa (barcha turlar) — doskada ko‘rsatish uchun
        $weightOut = $soldWeight + $lossWeight;
        $totalGain = round($weightOut - $stockedWeight, 2);

        // FCR / biomassa — faqat SAZAN (direktor hisobotidagi kabi, sodda)
        // Yakuniy fazada kontrol ahamiyatsiz — sotuv massasi + sotuv yemi
        $sazanMetrics = $this->sazanPerformance(
            $sazan,
            $feedKg,
            $durationDays,
            $phase === 'final' ? null : $latestControl
        );

        // Oraliq (hali sotuv yo‘q) — o‘sishni kontrol/sazan taxminidan ko‘rsat
        if ($phase !== 'final' && ($sazanMetrics['biomass_gain_kg'] ?? null) !== null) {
            $totalGain = (float) $sazanMetrics['biomass_gain_kg'];
        }

        return [
            'status' => $this->status ?: self::STATUS_ACTIVE,
            'phase' => $phase,
            'controls' => $controls->all(),
            'controls_count' => $controls->count(),
            'duration_days' => $durationDays,
            'stocked_on' => optional(Carbon::parse($stockedOn))->toDateString(),
            'sold_on' => optional(Carbon::parse($endOn))->toDateString(),
            'stocked_pieces' => $stockedPieces,
            'sold_pieces' => $soldPieces,
            'loss_pieces' => $lossPieces,
            'stocked_weight_kg' => round($stockedWeight, 2),
            'sold_weight_kg' => round($soldWeight, 2),
            'loss_weight_kg' => round($lossWeight, 2),
            'weight_gain_kg' => $totalGain,
            // Asosiy ko‘rsatkichlar — sazan
            'fcr' => $sazanMetrics['fcr'],
            'fcr_basis' => $sazanMetrics['basis'],
            'sazan' => $sazanMetrics,
            'fsr_percent' => $sazanMetrics['fsr_percent'],
            'lsr_percent' => $sazanMetrics['lsr_percent'],
            'survival_percent' => $sazanMetrics['fsr_percent'],
            'daily_gain_kg' => $sazanMetrics['daily_gain_kg'],
            'daily_gain_per_ha_kg' => $sazanMetrics['daily_gain_kg'] !== null
                ? round($sazanMetrics['daily_gain_kg'] / $areaHa, 3)
                : null,
            'yield_per_ha_kg' => round($soldWeight / $areaHa, 2),
            'feed_per_ha_kg' => round($feedKg / $areaHa, 2),
            'feed_per_day_kg' => round($feedKg / $durationDays, 2),
            'avg_start_weight_g' => $sazanMetrics['avg_start_weight_g'],
            'avg_end_weight_g' => $sazanMetrics['avg_end_weight_g'],
            'avg_daily_weight_gain_g' => $sazanMetrics['avg_daily_weight_gain_g'],
            'weight_multiplier' => $sazanMetrics['weight_multiplier'],
            'growth_curve' => $this->growthCurvePoints(
                $sazanMetrics['stocked_weight_kg'] ?? 0,
                $sazanMetrics['end_weight_kg'] ?? ($sazanMetrics['stocked_weight_kg'] ?? 0),
                $durationDays
            ),
            'feed_vs_gain' => [
                'feed_kg' => $feedKg,
                'gain_kg' => max((float) ($sazanMetrics['biomass_gain_kg'] ?? 0), 0),
            ],
            'fish' => $fishMetrics,
        ];
    }

    /**
     * Direktor kabinetidagi sazan hisoboti mantiği — soddalashtirilgan.
     * FCR = yem / sazan biomassa o‘sishi (kg).
     */
    private function sazanPerformance(
        ?VengerResultFish $sazan,
        float $feedKg,
        int $durationDays,
        ?array $latestControl = null
    ): array {
        $empty = [
            'count' => 0,
            'stocked_weight_kg' => 0,
            'end_weight_kg' => null,
            'biomass_gain_kg' => null,
            'fcr' => null,
            'basis' => 'none',
            'fsr_percent' => null,
            'lsr_percent' => null,
            'daily_gain_kg' => null,
            'avg_start_weight_g' => null,
            'avg_end_weight_g' => null,
            'avg_daily_weight_gain_g' => null,
            'weight_multiplier' => null,
            'feed_total_kg' => $feedKg,
        ];

        if (! $sazan) {
            return $empty;
        }

        $stockedKg = (float) $sazan->stocked_weight_kg;
        $stockedPieces = max((int) $sazan->stocked_pieces, 0);
        $soldKg = (float) $sazan->sold_weight_kg;
        $lossKg = (float) $sazan->loss_weight_kg;
        $soldPieces = (int) $sazan->sold_pieces;
        $lossPieces = (int) $sazan->loss_pieces;
        $currentKg = $sazan->current_weight_kg !== null ? (float) $sazan->current_weight_kg : null;

        $basis = 'none';
        $endKg = null;
        $avgEnd = null;

        if ($soldKg > 0 || $this->isFinal()) {
            $endKg = $soldKg + $lossKg;
            $basis = 'final';
            $endPieces = max($soldPieces, 1);
            $avgEnd = $endPieces > 0 ? round(($endKg * 1000) / $endPieces, 1) : null;
        } elseif ($latestControl) {
            $avgEnd = (float) $latestControl['avg_g'];
            $endKg = $stockedPieces > 0
                ? round($stockedPieces * ($avgEnd / 1000), 2)
                : null;
            $basis = 'mid';
        } elseif ($currentKg !== null && $currentKg > 0) {
            $endKg = $currentKg;
            $basis = 'mid';
            $endPieces = max((int) ($sazan->current_pieces ?: $stockedPieces), 1);
            $avgEnd = $endPieces > 0 ? round(($endKg * 1000) / $endPieces, 1) : null;
        }

        $biomassGain = $endKg !== null ? round($endKg - $stockedKg, 2) : null;
        $fcr = ($biomassGain !== null && $biomassGain > 0 && $feedKg > 0)
            ? round($feedKg / $biomassGain, 2)
            : null;

        $avgStart = $stockedPieces > 0 ? round(($stockedKg * 1000) / $stockedPieces, 1) : null;

        return [
            'count' => $stockedPieces,
            'stocked_weight_kg' => round($stockedKg, 2),
            'end_weight_kg' => $endKg !== null ? round($endKg, 2) : null,
            'sold_weight_kg' => round($soldKg, 2),
            'loss_weight_kg' => round($lossKg, 2),
            'current_weight_kg' => $endKg !== null && $basis === 'mid' ? round($endKg, 2) : $currentKg,
            'biomass_gain_kg' => $biomassGain,
            'fcr' => $fcr,
            'basis' => $basis,
            'fsr_percent' => $stockedPieces > 0 ? round(($soldPieces / $stockedPieces) * 100, 1) : null,
            'lsr_percent' => $stockedPieces > 0 ? round(($lossPieces / $stockedPieces) * 100, 1) : null,
            'daily_gain_kg' => $biomassGain !== null ? round($biomassGain / max($durationDays, 1), 3) : null,
            'avg_start_weight_g' => $avgStart,
            'avg_end_weight_g' => $avgEnd,
            'avg_daily_weight_gain_g' => ($avgStart !== null && $avgEnd !== null)
                ? round(($avgEnd - $avgStart) / max($durationDays, 1), 2)
                : null,
            'weight_multiplier' => ($stockedKg > 0 && $endKg !== null) ? round($endKg / $stockedKg, 2) : null,
            'feed_total_kg' => $feedKg,
        ];
    }

    private function growthCurvePoints(float $startKg, float $endKg, int $days): array
    {
        $points = [];
        $steps = min(max($days, 2), 12);
        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $ease = $t * $t * (3 - 2 * $t);
            $points[] = [
                'day' => (int) round($t * ($days - 1)) + 1,
                'weight_kg' => round($startKg + (($endKg - $startKg) * $ease), 2),
            ];
        }

        return $points;
    }
}
