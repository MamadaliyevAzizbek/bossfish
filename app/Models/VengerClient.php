<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VengerClient extends Model
{
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'address',
        'region',
        'district',
        'phone',
        'notes',
        'created_by',
    ];

    public static function fullName(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(VengerResult::class)->latest('sold_on');
    }
}
