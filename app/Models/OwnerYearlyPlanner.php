<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerYearlyPlanner extends Model
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

    /** @return array{dates: array<string, list<array{text: string, color: string|null}>>, long_term: list<array{id: string, text: string, color: string|null, completed_at: string|null}>} */
    public static function defaultContent(): array
    {
        return [
            'dates' => [],
            'long_term' => [],
        ];
    }

    /** @param  array<string, mixed>  $content */
    public static function normalizeContent(array $content): array
    {
        $dates = [];
        $incoming = is_array($content['dates'] ?? null) ? $content['dates'] : [];

        foreach ($incoming as $date => $cells) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            if (is_array($cells) && ! array_is_list($cells) && isset($cells['before_bomdod'])) {
                $normalizedDay = OwnerWeeklyPlanner::normalizeDaySlots($cells);
                if (OwnerWeeklyPlanner::daySlotsHaveText($normalizedDay)) {
                    $dates[$date] = $normalizedDay;
                }
                continue;
            }

            $normalized = OwnerWeeklyPlanner::normalizeSlotCells($cells);
            $hasText = false;
            foreach ($normalized as $cell) {
                if (trim((string) ($cell['text'] ?? '')) !== '') {
                    $hasText = true;
                    break;
                }
            }

            if ($hasText) {
                $dates[$date] = $normalized;
            }
        }

        ksort($dates);

        return [
            'dates' => $dates,
            'long_term' => self::normalizeLongTerm($content['long_term'] ?? []),
        ];
    }

    /**
     * @param  mixed  $items
     * @return list<array{id: string, text: string, color: string|null, completed_at: string|null}>
     */
    public static function normalizeLongTerm(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $id = is_string($item['id'] ?? null) && $item['id'] !== ''
                ? $item['id']
                : uniqid('lt_', true);

            $color = is_string($item['color'] ?? null) && in_array($item['color'], OwnerWeeklyPlanner::TONES, true)
                ? $item['color']
                : 'sky';

            $completedAt = null;
            if (! empty($item['completed_at']) && is_string($item['completed_at'])) {
                $completedAt = $item['completed_at'];
            }

            $out[] = [
                'id' => $id,
                'text' => $text,
                'color' => $color,
                'completed_at' => $completedAt,
            ];
        }

        return $out;
    }
}
