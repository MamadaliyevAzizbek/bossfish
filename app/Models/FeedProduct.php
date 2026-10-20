<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedProduct extends Model
{
    public const CATEGORY_FEED = 'feed';

    public const CATEGORY_VITAMIN = 'vitamin';

    protected $fillable = [
        'name',
        'price_per_kg',
        'category',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kg' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function rationItems(): HasMany
    {
        return $this->hasMany(FeedRationItem::class);
    }
}
