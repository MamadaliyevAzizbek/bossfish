<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerMonthlyPlanner extends Model
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

    /** @return list<int> */
    public static function dayKeys(): array
    {
        return range(1, 31);
    }

    /** @return array{days: array<string, list<array{text: string, color: string|null}>>} */
    public static function defaultContent(): array
    {
        return ['days' => []];
    }

    /** @param  array<string, mixed>  $content */
    public static function normalizeContent(array $content): array
    {
        $days = [];
        $incoming = is_array($content['days'] ?? null) ? $content['days'] : [];

        // Eski format (months) bo‘lsa — e’tiborsiz qoldiriladi (yangi model: 1–31 kun)
        foreach ($incoming as $day => $cells) {
            $dayNum = is_numeric($day) ? (int) $day : 0;
            if ($dayNum < 1 || $dayNum > 31) {
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
                $days[(string) $dayNum] = $normalized;
            }
        }

        ksort($days, SORT_NUMERIC);

        return ['days' => $days];
    }

    /**
     * Berilgan sanaga tegishli oylik vazifalar.
     * Qisqa oylarda (28/29/30): oy oxirgi kunida 31 (va mavjud bo‘lmagan) kunlardagi vazifalar ham chiqadi.
     *
     * @param  array<string, mixed>  $content
     * @return list<array{text: string, color: string|null, day?: int}>
     */
    public static function cellsForDate(array $content, Carbon $date): array
    {
        $normalized = self::normalizeContent($content);
        $dayOfMonth = (int) $date->day;
        $lastDay = (int) $date->copy()->endOfMonth()->day;

        $dayNumbers = [$dayOfMonth];
        if ($dayOfMonth === $lastDay && $lastDay < 31) {
            for ($d = $lastDay + 1; $d <= 31; $d++) {
                $dayNumbers[] = $d;
            }
        }

        $out = [];
        foreach ($dayNumbers as $dayNum) {
            $cells = $normalized['days'][(string) $dayNum] ?? [];
            foreach ($cells as $cell) {
                if (trim((string) ($cell['text'] ?? '')) === '') {
                    continue;
                }
                $out[] = [
                    'text' => (string) $cell['text'],
                    'color' => $cell['color'] ?? null,
                    'day' => $dayNum,
                ];
            }
        }

        return $out;
    }
}
