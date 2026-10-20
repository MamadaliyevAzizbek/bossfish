<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'pond_id',
        'user_id',
        'report_date',
        'feed_name',
        'water_temp',
        'ph_level',
        'feeding_kg',
        'wheat_bran_kg',
        'fertilizer_kg',
        'mortality_count',
        'fish_condition',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'water_temp' => 'decimal:2',
            'ph_level' => 'decimal:2',
            'feeding_kg' => 'decimal:2',
            'wheat_bran_kg' => 'decimal:2',
            'fertilizer_kg' => 'decimal:2',
            'mortality_count' => 'integer',
        ];
    }

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyReportItem::class);
    }
}
