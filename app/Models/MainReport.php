<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MainReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'pond_id',
        'user_id',
        'report_date',
        'test_time',
        'do_mg_l',
        'ph_level',
        'ammonia_mg_l',
        'nitrite_mg_l',
        'water_transparency_cm',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'do_mg_l' => 'decimal:3',
            'ph_level' => 'decimal:2',
            'ammonia_mg_l' => 'decimal:3',
            'nitrite_mg_l' => 'decimal:3',
            'water_transparency_cm' => 'decimal:2',
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

    public function fishWeights(): HasMany
    {
        return $this->hasMany(MainReportFishWeight::class);
    }
}
