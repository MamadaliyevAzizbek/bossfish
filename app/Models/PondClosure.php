<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PondClosure extends Model
{
    public const MODE_EARLY = 'early';

    public const MODE_FINAL = 'final';

    public const MODE_APPROVED = 'approved';

    protected $fillable = [
        'pond_id',
        'user_id',
        'mode',
        'closed_on',
        'estimated_stock',
        'actual_stock',
        'adjustments',
        'summary_snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'closed_on' => 'date',
            'estimated_stock' => 'array',
            'actual_stock' => 'array',
            'adjustments' => 'array',
            'summary_snapshot' => 'array',
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
