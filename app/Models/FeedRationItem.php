<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedRationItem extends Model
{
    protected $fillable = [
        'feed_ration_id',
        'feed_product_id',
        'qty_per_100kg',
    ];

    protected function casts(): array
    {
        return [
            'qty_per_100kg' => 'decimal:3',
        ];
    }

    public function ration(): BelongsTo
    {
        return $this->belongsTo(FeedRation::class, 'feed_ration_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FeedProduct::class, 'feed_product_id');
    }
}
