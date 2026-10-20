<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerDailyExtra extends Model
{
    protected $fillable = [
        'user_id',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{dates: array<string, list<array{text: string, color: string|null}>>} */
    public static function defaultContent(): array
    {
        return ['dates' => []];
    }

    /** @param  array<string, mixed>  $content */
    public static function normalizeContent(array $content): array
    {
        return OwnerYearlyPlanner::normalizeContent($content);
    }
}
