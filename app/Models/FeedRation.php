<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedRation extends Model
{
    protected $fillable = [
        'name',
        'kop_narx',
        'sale_price_per_kg',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kop_narx' => 'decimal:2',
            'sale_price_per_kg' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedRationItem::class)->orderBy('id');
    }
}
