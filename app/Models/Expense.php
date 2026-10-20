<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'pond_id',
        'user_id',
        'category',
        'amount',
        'quantity',
        'unit_price',
        'expense_date',
        'title',
        'description',
        'warehouse_item_id',
        'fish_operation_id',
    ];

    protected $appends = [
        'scope',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function getScopeAttribute(): string
    {
        return $this->pond_id ? 'pond' : 'farm';
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }

    public function fishOperation(): BelongsTo
    {
        return $this->belongsTo(FishOperation::class);
    }
}
