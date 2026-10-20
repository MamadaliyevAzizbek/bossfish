<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class WarehouseItem extends Model
{
    protected $fillable = [
        'farm_id',
        'category',
        'name',
        'unit',
        'quantity',
        'photo_path',
        'created_by',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected $hidden = [
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dailyReportItems(): HasMany
    {
        return $this->hasMany(DailyReportItem::class);
    }
}
