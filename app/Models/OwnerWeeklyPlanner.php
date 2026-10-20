<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerWeeklyPlanner extends Model
{
    public const TONES = ['sky', 'mint', 'sand', 'lilac', 'red', 'ocean'];

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

    /** @return list<string> */
    public static function slotKeys(): array
    {
        return [
            'before_bomdod', 'after_bomdod',
            'before_peshin', 'after_peshin',
            'before_asr', 'after_asr',
            'before_shom', 'after_shom',
            'before_xufton', 'after_xufton',
        ];
    }

    /** @return array<string, array<string, list<array{text: string, color: string|null}>>> */
    public static function defaultContent(): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $content = ['days' => []];

        foreach ($days as $day) {
            $content['days'][$day] = [];
            foreach (self::slotKeys() as $slotKey) {
                $content['days'][$day][$slotKey] = [self::emptyCell()];
            }
        }

        return $content;
    }

    /** @return array{text: string, color: string|null} */
    public static function emptyCell(): array
    {
        return ['text' => '', 'color' => null];
    }

    /** @return list<array{text: string, color: string|null}> */
    public static function normalizeSlotCells(mixed $value, int $slotIndex = 0): array
    {
        if (is_array($value) && array_is_list($value)) {
            $cells = [];
            foreach ($value as $index => $item) {
                $cells[] = self::normalizeCell($item, self::TONES[($slotIndex + $index) % count(self::TONES)]);
            }

            return $cells !== [] ? $cells : [self::emptyCell()];
        }

        if (is_string($value)) {
            return trim($value) === '' ? [self::emptyCell()] : [self::normalizeCell($value, 'sky')];
        }

        return [self::emptyCell()];
    }

    /** @return array{text: string, color: string|null} */
    public static function normalizeCell(mixed $item, ?string $fallbackTone = null): array
    {
        if (is_array($item)) {
            $text = is_string($item['text'] ?? null) ? $item['text'] : '';
            $color = is_string($item['color'] ?? null) && in_array($item['color'], self::TONES, true)
                ? $item['color']
                : null;

            if (trim($text) === '') {
                $color = null;
            } elseif (! $color && $fallbackTone) {
                $color = $fallbackTone;
            }

            return ['text' => $text, 'color' => $color];
        }

        if (is_string($item)) {
            $text = $item;

            return [
                'text' => $text,
                'color' => trim($text) === '' ? null : ($fallbackTone ?? 'sky'),
            ];
        }

        return self::emptyCell();
    }

    /** @return array<string, list<array{text: string, color: string|null}>> */
    public static function emptyDaySlots(): array
    {
        $day = [];
        foreach (self::slotKeys() as $slotKey) {
            $day[$slotKey] = [self::emptyCell()];
        }

        return $day;
    }

    /** @param  array<string, mixed>  $dayPayload */
    public static function normalizeDaySlots(array $dayPayload): array
    {
        if ($dayPayload !== [] && array_is_list($dayPayload)) {
            $day = self::emptyDaySlots();
            $day['after_bomdod'] = self::normalizeSlotCells($dayPayload);

            return $day;
        }

        $day = self::emptyDaySlots();
        $slotIndex = 0;
        foreach (self::slotKeys() as $slotKey) {
            $day[$slotKey] = self::normalizeSlotCells($dayPayload[$slotKey] ?? [self::emptyCell()], $slotIndex);
            $slotIndex++;
        }

        return $day;
    }

    /** @param  array<string, list<array{text: string, color: string|null}>>  $daySlots */
    public static function daySlotsHaveText(array $daySlots): bool
    {
        foreach ($daySlots as $cells) {
            foreach ($cells as $cell) {
                if (trim((string) ($cell['text'] ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }
}
