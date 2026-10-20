<?php

namespace App\Models;

use App\Support\FishTypes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VengerResultFish extends Model
{
    protected $table = 'venger_result_fish';

    protected $fillable = [
        'venger_result_id',
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
    ];

    protected function casts(): array
    {
        return [
            'stocked_on' => 'date',
            'current_on' => 'date',
            'sold_on' => 'date',
            'loss_on' => 'date',
            'stocked_pieces' => 'integer',
            'current_pieces' => 'integer',
            'sold_pieces' => 'integer',
            'loss_pieces' => 'integer',
            'stocked_weight_kg' => 'float',
            'current_weight_kg' => 'float',
            'sold_weight_kg' => 'float',
            'loss_weight_kg' => 'float',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(VengerResult::class, 'venger_result_id');
    }

    public function label(): string
    {
        return FishTypes::labels()[$this->fish_type] ?? $this->fish_type;
    }

    public function metrics(): array
    {
        $stockedPieces = max((int) $this->stocked_pieces, 0);
        $soldPieces = max((int) $this->sold_pieces, 0);
        $lossPieces = max((int) $this->loss_pieces, 0);
        $stockedWeight = (float) $this->stocked_weight_kg;
        $soldWeight = (float) $this->sold_weight_kg;
        $lossWeight = (float) $this->loss_weight_kg;

        $endDate = $this->sold_on ?: ($this->loss_on ?: $this->stocked_on);
        $durationDays = max(
            (int) Carbon::parse($this->stocked_on)->diffInDays(Carbon::parse($endDate)) + 1,
            1
        );

        $accountedPieces = $soldPieces + $lossPieces;
        $currentWeight = $this->current_weight_kg !== null ? (float) $this->current_weight_kg : null;
        $weightOut = ($soldWeight > 0 || $lossWeight > 0)
            ? ($soldWeight + $lossWeight)
            : ($currentWeight ?? $stockedWeight);
        $weightGain = round($weightOut - $stockedWeight, 2);

        return [
            'fish_type' => $this->fish_type,
            'label' => $this->label(),
            'duration_days' => $durationDays,
            'weight_gain_kg' => $weightGain,
            'fsr_percent' => $stockedPieces > 0 ? round(($soldPieces / $stockedPieces) * 100, 2) : null,
            'lsr_percent' => $stockedPieces > 0 ? round(($lossPieces / $stockedPieces) * 100, 2) : null,
            'accounted_pieces' => $accountedPieces,
            'unaccounted_pieces' => max($stockedPieces - $accountedPieces, 0),
            'avg_start_weight_g' => $stockedPieces > 0 ? round(($stockedWeight * 1000) / $stockedPieces, 1) : null,
            'avg_sold_weight_g' => $soldPieces > 0 ? round(($soldWeight * 1000) / $soldPieces, 1) : null,
            'avg_daily_weight_gain_g' => ($stockedPieces > 0 && $soldPieces > 0)
                ? round(((($soldWeight * 1000) / $soldPieces) - (($stockedWeight * 1000) / $stockedPieces)) / $durationDays, 2)
                : null,
        ];
    }
}
