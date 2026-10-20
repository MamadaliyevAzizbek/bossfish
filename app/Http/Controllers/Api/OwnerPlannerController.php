<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OwnerDailyExtra;
use App\Models\OwnerInboxTask;
use App\Models\OwnerMonthlyPlanner;
use App\Models\OwnerWeeklyPlanner;
use App\Models\OwnerYearlyPlanner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerPlannerController extends Controller
{
    private const WEEKDAY_KEYS = [
        1 => 'mon',
        2 => 'tue',
        3 => 'wed',
        4 => 'thu',
        5 => 'fri',
        6 => 'sat',
        0 => 'sun',
    ];

    public function show(Request $request): JsonResponse
    {
        $planner = OwnerWeeklyPlanner::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['content' => OwnerWeeklyPlanner::defaultContent()]
        );

        return response()->json([
            'content' => $this->normalizeWeeklyContent($planner->content ?? []),
            'updated_at' => optional($planner->updated_at)->toIso8601String(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|array',
            'content.days' => 'required|array',
        ]);

        $planner = OwnerWeeklyPlanner::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['content' => OwnerWeeklyPlanner::defaultContent()]
        );

        $planner->update([
            'content' => $this->normalizeWeeklyContent($data['content']),
        ]);

        return response()->json([
            'content' => $planner->content,
            'updated_at' => optional($planner->updated_at)->toIso8601String(),
            'message' => 'Haftalik kundalik saqlandi.',
        ]);
    }

    public function showYearly(Request $request): JsonResponse
    {
        $planner = OwnerYearlyPlanner::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['content' => OwnerYearlyPlanner::defaultContent()]
        );

        return response()->json([
            'content' => OwnerYearlyPlanner::normalizeContent($planner->content ?? []),
            'updated_at' => optional($planner->updated_at)->toIso8601String(),
        ]);
    }

    public function updateYearly(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => 'nullable|array',
            'content.dates' => 'nullable|array',
            'content.long_term' => 'nullable|array',
            'date' => 'nullable|date_format:Y-m-d',
            'cells' => 'nullable|array',
            'long_term' => 'nullable|array',
        ]);

        $planner = OwnerYearlyPlanner::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['content' => OwnerYearlyPlanner::defaultContent()]
        );

        $content = OwnerYearlyPlanner::normalizeContent($planner->content ?? []);

        if (array_key_exists('long_term', $data) && is_array($data['long_term'])) {
            $content['long_term'] = OwnerYearlyPlanner::normalizeLongTerm($data['long_term']);
        }

        if (! empty($data['date'])) {
            $cells = OwnerWeeklyPlanner::normalizeSlotCells($data['cells'] ?? []);
            $hasText = collect($cells)->contains(fn ($cell) => trim((string) ($cell['text'] ?? '')) !== '');
            if ($hasText) {
                $content['dates'][$data['date']] = $cells;
            } else {
                unset($content['dates'][$data['date']]);
            }
            ksort($content['dates']);
        } elseif (isset($data['content'])) {
            $merged = OwnerYearlyPlanner::normalizeContent($data['content']);
            $content['dates'] = $merged['dates'];
            if (array_key_exists('long_term', $data['content'])) {
                $content['long_term'] = $merged['long_term'];
            }
        } elseif (! array_key_exists('long_term', $data)) {
            throw ValidationException::withMessages([
                'content' => ['content, date+cells yoki long_term kerak.'],
            ]);
        }

        $planner->update(['content' => $content]);

        return response()->json([
            'content' => OwnerYearlyPlanner::normalizeContent($planner->content ?? []),
            'updated_at' => optional($planner->updated_at)->toIso8601String(),
            'message' => 'Yillik kundalik saqlandi.',
        ]);
    }

    public function showMonthly(Request $request): JsonResponse
    {
        $planner = OwnerMonthlyPlanner::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['content' => OwnerMonthlyPlanner::defaultContent()]
        );

        return response()->json([
            'content' => OwnerMonthlyPlanner::normalizeContent($planner->content ?? []),
            'updated_at' => optional($planner->updated_at)->toIso8601String(),
        ]);
    }

    public function updateMonthly(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => 'nullable|array',
            'content.days' => 'nullable|array',
            'day' => 'nullable|integer|min:1|max:31',
            'cells' => 'nullable|array',
        ]);

        $planner = OwnerMonthlyPlanner::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['content' => OwnerMonthlyPlanner::defaultContent()]
        );

        $content = OwnerMonthlyPlanner::normalizeContent($planner->content ?? []);

        if (! empty($data['day'])) {
            $key = (string) (int) $data['day'];
            $cells = OwnerWeeklyPlanner::normalizeSlotCells($data['cells'] ?? []);
            $hasText = collect($cells)->contains(fn ($cell) => trim((string) ($cell['text'] ?? '')) !== '');
            if ($hasText) {
                $content['days'][$key] = $cells;
            } else {
                unset($content['days'][$key]);
            }
            ksort($content['days'], SORT_NUMERIC);
        } elseif (isset($data['content'])) {
            $content = OwnerMonthlyPlanner::normalizeContent($data['content']);
        } else {
            throw ValidationException::withMessages([
                'content' => ['content yoki day+cells kerak.'],
            ]);
        }

        $planner->update(['content' => $content]);

        return response()->json([
            'content' => OwnerMonthlyPlanner::normalizeContent($planner->content ?? []),
            'updated_at' => optional($planner->updated_at)->toIso8601String(),
            'message' => 'Oylik kundalik saqlandi.',
        ]);
    }

    public function showDaily(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $weekly = OwnerWeeklyPlanner::firstOrCreate(
            ['user_id' => $userId],
            ['content' => OwnerWeeklyPlanner::defaultContent()]
        );
        $yearly = OwnerYearlyPlanner::firstOrCreate(
            ['user_id' => $userId],
            ['content' => OwnerYearlyPlanner::defaultContent()]
        );
        $monthly = OwnerMonthlyPlanner::firstOrCreate(
            ['user_id' => $userId],
            ['content' => OwnerMonthlyPlanner::defaultContent()]
        );
        $extras = OwnerDailyExtra::firstOrCreate(
            ['user_id' => $userId],
            ['content' => OwnerDailyExtra::defaultContent()]
        );

        $weeklyContent = $this->normalizeWeeklyContent($weekly->content ?? []);
        $yearlyContent = OwnerYearlyPlanner::normalizeContent($yearly->content ?? []);
        $monthlyContent = OwnerMonthlyPlanner::normalizeContent($monthly->content ?? []);
        $extrasContent = OwnerDailyExtra::normalizeContent($extras->content ?? []);

        $inbox = OwnerInboxTask::query()
            ->where('user_id', $userId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'text', 'color', 'sort_order', 'created_at']);

        $completed = OwnerInboxTask::query()
            ->where('user_id', $userId)
            ->completed()
            ->orderByDesc('completed_at')
            ->limit(30)
            ->get(['id', 'text', 'color', 'completed_at']);

        return response()->json([
            'today' => $this->buildDayBundle($today, $weeklyContent, $yearlyContent, $monthlyContent, $extrasContent),
            'tomorrow' => $this->buildDayBundle($tomorrow, $weeklyContent, $yearlyContent, $monthlyContent, $extrasContent),
            'inbox' => $inbox,
            'completed' => $completed,
        ]);
    }

    public function updateDailyExtras(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'schedule' => 'nullable|array',
            'cells' => 'nullable|array',
        ]);

        $extras = OwnerDailyExtra::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['content' => OwnerDailyExtra::defaultContent()]
        );

        $content = is_array($extras->content) ? $extras->content : OwnerDailyExtra::defaultContent();
        if (! is_array($content['dates'] ?? null)) {
            $content['dates'] = [];
        }

        if (isset($data['schedule'])) {
            $daySlots = OwnerWeeklyPlanner::normalizeDaySlots($data['schedule']);
            if (OwnerWeeklyPlanner::daySlotsHaveText($daySlots)) {
                $content['dates'][$data['date']] = $daySlots;
            } else {
                unset($content['dates'][$data['date']]);
            }
        } else {
            $cells = OwnerWeeklyPlanner::normalizeSlotCells($data['cells'] ?? []);
            $hasText = collect($cells)->contains(fn ($cell) => trim((string) ($cell['text'] ?? '')) !== '');
            if ($hasText) {
                $day = OwnerWeeklyPlanner::emptyDaySlots();
                $day['after_bomdod'] = $cells;
                $content['dates'][$data['date']] = $day;
            } else {
                unset($content['dates'][$data['date']]);
            }
        }

        ksort($content['dates']);
        $extras->update(['content' => ['dates' => $content['dates']]]);

        $saved = $content['dates'][$data['date']] ?? OwnerWeeklyPlanner::emptyDaySlots();

        return response()->json([
            'date' => $data['date'],
            'schedule' => OwnerWeeklyPlanner::normalizeDaySlots(is_array($saved) ? $saved : []),
            'message' => 'Kunlik jadval saqlandi.',
        ]);
    }

    public function storeInbox(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => 'required|string|max:2000',
            'color' => 'nullable|string|in:sky,mint,sand,lilac,red,ocean',
        ]);

        $maxOrder = (int) OwnerInboxTask::query()
            ->where('user_id', $request->user()->id)
            ->active()
            ->max('sort_order');

        $task = OwnerInboxTask::create([
            'user_id' => $request->user()->id,
            'text' => trim($data['text']),
            'color' => $data['color'] ?? 'sky',
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json($task, 201);
    }

    public function completeInbox(Request $request, OwnerInboxTask $inboxTask): JsonResponse
    {
        if ((int) $inboxTask->user_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['task' => ['Bu vazifa sizniki emas.']]);
        }

        if ($inboxTask->completed_at) {
            return response()->json($inboxTask);
        }

        $inboxTask->update(['completed_at' => now()]);

        return response()->json($inboxTask);
    }

    public function destroyInbox(Request $request, OwnerInboxTask $inboxTask): JsonResponse
    {
        if ((int) $inboxTask->user_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['task' => ['Bu vazifa sizniki emas.']]);
        }

        $inboxTask->delete();

        return response()->json(['message' => 'O‘chirildi']);
    }

    /** @param  array<string, mixed>  $content */
    private function normalizeWeeklyContent(array $content): array
    {
        $defaults = OwnerWeeklyPlanner::defaultContent();
        $days = $content['days'] ?? [];

        foreach ($defaults['days'] as $dayKey => $slots) {
            $dayPayload = is_array($days[$dayKey] ?? null) ? $days[$dayKey] : [];
            $slotIndex = 0;

            foreach ($slots as $slotKey => $defaultValue) {
                $defaults['days'][$dayKey][$slotKey] = OwnerWeeklyPlanner::normalizeSlotCells(
                    $dayPayload[$slotKey] ?? $defaultValue,
                    $slotIndex
                );
                $slotIndex++;
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $weekly
     * @param  array<string, mixed>  $yearly
     * @param  array<string, mixed>  $monthly
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function buildDayBundle(Carbon $day, array $weekly, array $yearly, array $monthly, array $extras): array
    {
        $date = $day->toDateString();
        $weekdayKey = self::WEEKDAY_KEYS[(int) $day->dayOfWeek] ?? 'mon';
        $weeklyDay = OwnerWeeklyPlanner::normalizeDaySlots($weekly['days'][$weekdayKey] ?? []);
        $extraRaw = $extras['dates'][$date] ?? null;

        if (is_array($extraRaw) && (isset($extraRaw['before_bomdod']) || (! array_is_list($extraRaw) && $extraRaw !== []))) {
            $schedule = OwnerWeeklyPlanner::normalizeDaySlots($extraRaw);
        } elseif (is_array($extraRaw) && array_is_list($extraRaw)) {
            $schedule = $weeklyDay;
            $legacy = OwnerWeeklyPlanner::normalizeSlotCells($extraRaw);
            foreach ($legacy as $cell) {
                if (trim((string) ($cell['text'] ?? '')) !== '') {
                    $schedule['after_bomdod'][] = $cell;
                }
            }
            if ($schedule['after_bomdod'] === []) {
                $schedule['after_bomdod'] = [OwnerWeeklyPlanner::emptyCell()];
            }
        } else {
            $schedule = $weeklyDay;
        }

        return [
            'date' => $date,
            'weekday' => $weekdayKey,
            'label' => $day->translatedFormat('l, d.m.Y'),
            'schedule' => $schedule,
            'from_yearly' => $this->cellsWithText($yearly['dates'][$date] ?? []),
            'from_monthly' => OwnerMonthlyPlanner::cellsForDate($monthly, $day),
        ];
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<array{text: string, color: string|null}>
     */
    private function cellsWithText(array $cells): array
    {
        if (is_array($cells) && isset($cells['before_bomdod'])) {
            $flat = [];
            foreach (OwnerWeeklyPlanner::normalizeDaySlots($cells) as $slotCells) {
                foreach ($slotCells as $cell) {
                    if (trim((string) ($cell['text'] ?? '')) !== '') {
                        $flat[] = $cell;
                    }
                }
            }

            return $flat;
        }

        return array_values(array_filter(
            OwnerWeeklyPlanner::normalizeSlotCells($cells),
            fn ($cell) => trim((string) ($cell['text'] ?? '')) !== ''
        ));
    }
}
