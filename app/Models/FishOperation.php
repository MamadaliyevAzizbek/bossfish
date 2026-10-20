<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FishOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'pond_id',
        'user_id',
        'type',
        'fish_type',
        'quantity',
        'average_weight_kg',
        'total_weight_kg',
        'amount',
        'title',
        'notes',
        'operated_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'average_weight_kg' => 'decimal:3',
            'total_weight_kg' => 'decimal:3',
            'amount' => 'decimal:2',
            'operated_at' => 'date',
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
}
