<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\MainReport;
use App\Models\Pond;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Kundalik ish eslatmalari (O‘zbekiston vaqti):
 * - Yem/dori: har kuni soat 20:00 dan keyin, bugun yozilmasa
 * - Suv test: haftada ~2 marta → oxirgi testdan 4 kun o‘tsa
 * - Kontrol (tortish): oyda 1 marta → oxirgi tortishdan 30 kun o‘tsa
 */
class WorkScheduleReminders
{
    public const TZ = 'Asia/Tashkent';

    public const FEED_REMIND_HOUR = 20;

    /** Haftada 2 marta ≈ har 3–4 kunda */
    public const WATER_MAX_GAP_DAYS = 4;

    public const WEIGH_MAX_GAP_DAYS = 30;

    /**
     * @param  Collection<int, int>|array<int>  $farmIds
     * @return list<array<string, mixed>>
     */
    public static function forFarms($farmIds, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now(self::TZ))->copy()->timezone(self::TZ);
        $farmIds = collect($farmIds)->filter()->values();
        if ($farmIds->isEmpty()) {
            return [];
        }

        $ponds = Pond::query()
            ->with(['farm:id,name'])
            ->whereIn('farm_id', $farmIds)
            ->whereNull('closed_at')
            ->where('current_stock', '>', 0)
            ->get(['id', 'name', 'farm_id', 'current_stock', 'created_at']);

        if ($ponds->isEmpty()) {
            return [];
        }

        $pondIds = $ponds->pluck('id');
        $today = $now->toDateString();

        $fedToday = DailyReport::query()
            ->whereIn('pond_id', $pondIds)
            ->whereDate('report_date', $today)
            ->pluck('pond_id')
            ->unique()
            ->all();
        $fedSet = array_fill_keys($fedToday, true);

        $lastWater = self::lastWaterTestDates($pondIds);
        $lastWeigh = self::lastWeighDates($pondIds);

        $alerts = [];
        $afterFeedHour = $now->hour >= self::FEED_REMIND_HOUR;

        foreach ($ponds as $pond) {
            $base = [
                'farmId' => $pond->farm_id,
                'pondId' => $pond->id,
                'farmName' => $pond->farm?->name,
                'pondName' => $pond->name,
            ];

            if ($afterFeedHour && ! isset($fedSet[$pond->id])) {
                $alerts[] = [
                    ...$base,
                    'type' => 'feed-reminder',
                    'tone' => 'warning',
                    'message_key' => 'workReminderFeed',
                    'message_params' => ['pond' => $pond->name],
                    'itemId' => 'feed-'.$pond->id.'-'.$today,
                    'route' => 'director-daily',
                ];
            }

            $todayStart = $now->copy()->startOfDay();
            $waterAt = $lastWater[$pond->id] ?? null;
            $waterDays = $waterAt
                ? (int) $waterAt->diffInDays($todayStart, true)
                : self::WATER_MAX_GAP_DAYS + 1;
            // Yangi havza: ochilganiga 4 kun bo‘lmaguncha suv eslatmasin
            $pondAgeDays = $pond->created_at
                ? (int) Carbon::parse($pond->created_at)->timezone(self::TZ)->startOfDay()->diffInDays($todayStart, true)
                : 99;
            if ($pondAgeDays >= self::WATER_MAX_GAP_DAYS && $waterDays >= self::WATER_MAX_GAP_DAYS) {
                $alerts[] = [
                    ...$base,
                    'type' => 'water-test-reminder',
                    'tone' => 'warning',
                    'message_key' => 'workReminderWater',
                    'message_params' => [
                        'pond' => $pond->name,
                        'days' => $waterAt ? $waterDays : null,
                    ],
                    'itemId' => 'water-'.$pond->id,
                    'route' => 'director-main-report',
                ];
            }

            $weighAt = $lastWeigh[$pond->id] ?? null;
            $weighDays = $weighAt
                ? (int) $weighAt->diffInDays($todayStart, true)
                : self::WEIGH_MAX_GAP_DAYS + 1;
            $pondAgeForWeigh = $pond->created_at
                ? (int) Carbon::parse($pond->created_at)->timezone(self::TZ)->startOfDay()->diffInDays($todayStart, true)
                : 99;
            if ($pondAgeForWeigh >= self::WEIGH_MAX_GAP_DAYS && $weighDays >= self::WEIGH_MAX_GAP_DAYS) {
                $alerts[] = [
                    ...$base,
                    'type' => 'weigh-reminder',
                    'tone' => 'warning',
                    'message_key' => 'workReminderWeigh',
                    'message_params' => [
                        'pond' => $pond->name,
                        'days' => $weighAt ? (int) $weighDays : null,
                    ],
                    'itemId' => 'weigh-'.$pond->id,
                    'route' => 'director-main-report',
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, int>  $pondIds
     * @return array<int, Carbon>
     */
    private static function lastWaterTestDates(Collection $pondIds): array
    {
        $rows = MainReport::query()
            ->whereIn('pond_id', $pondIds)
            ->where(function ($q) {
                $q->whereNotNull('do_mg_l')
                    ->orWhereNotNull('ph_level')
                    ->orWhereNotNull('ammonia_mg_l')
                    ->orWhereNotNull('nitrite_mg_l')
                    ->orWhereNotNull('water_transparency_cm');
            })
            ->selectRaw('pond_id, MAX(report_date) as last_date')
            ->groupBy('pond_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->pond_id] = Carbon::parse($row->last_date)->timezone(self::TZ)->startOfDay();
        }

        return $map;
    }

    /**
     * @param  Collection<int, int>  $pondIds
     * @return array<int, Carbon>
     */
    private static function lastWeighDates(Collection $pondIds): array
    {
        $rows = MainReport::query()
            ->whereIn('pond_id', $pondIds)
            ->whereHas('fishWeights')
            ->selectRaw('pond_id, MAX(report_date) as last_date')
            ->groupBy('pond_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->pond_id] = Carbon::parse($row->last_date)->timezone(self::TZ)->startOfDay();
        }

        return $map;
    }
}
