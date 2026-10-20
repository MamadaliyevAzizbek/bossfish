<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MainReportFishWeight extends Model
{
    use HasFactory;

    protected $fillable = [
        'main_report_id',
        'fish_type',
        'average_weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'average_weight_kg' => 'decimal:3',
        ];
    }

    public function mainReport(): BelongsTo
    {
        return $this->belongsTo(MainReport::class);
    }
}
