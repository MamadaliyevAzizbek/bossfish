<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    protected $fillable = [
        'name',
        'region',
        'director_id',
        'created_by',
        'status',
        'notes',
    ];

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ponds(): HasMany
    {
        return $this->hasMany(Pond::class);
    }

    public function warehouseItems(): HasMany
    {
        return $this->hasMany(WarehouseItem::class);
    }

    public function feedTypes(): HasMany
    {
        return $this->hasMany(FeedType::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(FarmInvestment::class);
    }

    public function financeSections(): HasMany
    {
        return $this->hasMany(FinanceSection::class);
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'farm_worker')
            ->withPivot(['position', 'hired_at'])
            ->withTimestamps();
    }
}
