<?php

namespace App\Services;

use App\Models\MainReport;
use Carbon\Carbon;

/**
 * Suv test normalari — KPR 3-in-1 test-kit (pH, NH3-N, NO2-) asosida.
 * Kit diapazonlari: pH 6.5–9.5 · ammiak 0.2–15 mg/l · nitrit 0.005–0.3 mg/l.
 * DO va shaffoflik alohida o‘lchagichlar uchun; soat bandiga bog‘liq.
 */
class WaterQualityStandards
{
    public const LEVEL_OK = 'ok';

    public const LEVEL_CAUTION = 'caution';

    public const LEVEL_DANGER = 'danger';

    /** @return array{key: string, from: string, to: string} */
    public static function timeBand(?string $testTime): array
    {
        $minutes = self::minutesOfDay($testTime);
        if ($minutes === null) {
            return ['key' => 'unknown', 'from' => '', 'to' => ''];
        }

        // Ertalab — DO eng past, pH pastroq
        if ($minutes >= 5 * 60 && $minutes < 10 * 60) {
            return ['key' => 'morning', 'from' => '05:00', 'to' => '10:00'];
        }
        // Kunduzi
        if ($minutes >= 10 * 60 && $minutes < 16 * 60) {
            return ['key' => 'day', 'from' => '10:00', 'to' => '16:00'];
        }
        // Kechqurun — DO yuqori, pH yuqori (ammiak xavfi)
        if ($minutes >= 16 * 60 && $minutes < 21 * 60) {
            return ['key' => 'evening', 'from' => '16:00', 'to' => '21:00'];
        }

        return ['key' => 'night', 'from' => '21:00', 'to' => '05:00'];
    }

    /**
     * Forma shkalalari va izohlar uchun (frontend).
     *
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        return [
            'kit' => [
                'name' => 'KPR 3-in-1',
                'ph' => ['min' => 6.5, 'max' => 9.5],
                'ammonia_mg_l' => ['min' => 0.2, 'max' => 15.0],
                'nitrite_mg_l' => ['min' => 0.005, 'max' => 0.3],
            ],
            'bands' => [
                ['key' => 'morning', 'from' => '05:00', 'to' => '10:00'],
                ['key' => 'day', 'from' => '10:00', 'to' => '16:00'],
                ['key' => 'evening', 'from' => '16:00', 'to' => '21:00'],
                ['key' => 'night', 'from' => '21:00', 'to' => '05:00'],
            ],
            'metrics' => [
                'do_mg_l' => [
                    'unit' => 'mg/l',
                    'scale_min' => 0,
                    'scale_max' => 12,
                    'bands' => [
                        'morning' => ['ok_min' => 4.0, 'caution_min' => 2.5, 'danger_below' => 2.5],
                        'day' => ['ok_min' => 5.0, 'caution_min' => 3.0, 'danger_below' => 3.0],
                        'evening' => ['ok_min' => 5.0, 'caution_min' => 3.5, 'danger_below' => 3.5],
                        'night' => ['ok_min' => 4.0, 'caution_min' => 2.5, 'danger_below' => 2.5],
                        'unknown' => ['ok_min' => 4.0, 'caution_min' => 3.0, 'danger_below' => 3.0],
                    ],
                ],
                'ph_level' => [
                    'unit' => '',
                    'scale_min' => 6.5,
                    'scale_max' => 9.5,
                    'kit_min' => 6.5,
                    'kit_max' => 9.5,
                    'bands' => [
                        // Ertalab pH pastroq bo‘lishi normal
                        'morning' => ['ok' => [6.5, 8.0], 'caution' => [8.0, 8.6], 'danger_above' => 8.6, 'danger_below' => 6.5],
                        'day' => ['ok' => [6.8, 8.5], 'caution' => [8.5, 9.0], 'danger_above' => 9.0, 'danger_below' => 6.5],
                        // Kechqurun biroz yuqoriroq ruxsat — lekin 9.2+ xavfli (ammiak)
                        'evening' => ['ok' => [7.0, 8.8], 'caution' => [8.8, 9.2], 'danger_above' => 9.2, 'danger_below' => 6.5],
                        'night' => ['ok' => [6.5, 8.5], 'caution' => [8.5, 9.0], 'danger_above' => 9.0, 'danger_below' => 6.5],
                        'unknown' => ['ok' => [6.5, 8.5], 'caution' => [8.5, 9.0], 'danger_above' => 9.0, 'danger_below' => 6.5],
                    ],
                ],
                'ammonia_mg_l' => [
                    'unit' => 'mg/l',
                    'scale_min' => 0,
                    'scale_max' => 3,
                    'kit_min' => 0.2,
                    'kit_max' => 15.0,
                    // Kit 0.2 dan boshlanadi — undan past = aniqlanmagan = yaxshi
                    'bands' => [
                        'morning' => ['ok_max' => 0.5, 'caution_max' => 1.0],
                        'day' => ['ok_max' => 0.5, 'caution_max' => 1.0],
                        // Kechqurun yuqori pH bilan birga — qattiqroq
                        'evening' => ['ok_max' => 0.3, 'caution_max' => 0.7],
                        'night' => ['ok_max' => 0.5, 'caution_max' => 1.0],
                        'unknown' => ['ok_max' => 0.5, 'caution_max' => 1.0],
                    ],
                ],
                'nitrite_mg_l' => [
                    'unit' => 'mg/l',
                    'scale_min' => 0,
                    'scale_max' => 0.3,
                    'kit_min' => 0.005,
                    'kit_max' => 0.3,
                    'bands' => [
                        'morning' => ['ok_max' => 0.05, 'caution_max' => 0.15],
                        'day' => ['ok_max' => 0.05, 'caution_max' => 0.15],
                        'evening' => ['ok_max' => 0.05, 'caution_max' => 0.15],
                        'night' => ['ok_max' => 0.05, 'caution_max' => 0.15],
                        'unknown' => ['ok_max' => 0.05, 'caution_max' => 0.15],
                    ],
                ],
                'water_transparency_cm' => [
                    'unit' => 'cm',
                    'scale_min' => 0,
                    'scale_max' => 80,
                    'bands' => [
                        'morning' => ['ok' => [25, 45], 'caution_low' => [15, 25], 'caution_high' => [45, 60]],
                        'day' => ['ok' => [25, 45], 'caution_low' => [15, 25], 'caution_high' => [45, 60]],
                        'evening' => ['ok' => [25, 45], 'caution_low' => [15, 25], 'caution_high' => [45, 60]],
                        'night' => ['ok' => [25, 45], 'caution_low' => [15, 25], 'caution_high' => [45, 60]],
                        'unknown' => ['ok' => [25, 45], 'caution_low' => [15, 25], 'caution_high' => [45, 60]],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array{
     *   test_time?: string|null,
     *   do_mg_l?: float|string|null,
     *   ph_level?: float|string|null,
     *   ammonia_mg_l?: float|string|null,
     *   nitrite_mg_l?: float|string|null,
     *   water_transparency_cm?: float|string|null
     * }  $readings
     * @return array{
     *   level: string,
     *   band: array{key: string, from: string, to: string},
     *   issues: list<array{metric: string, level: string, value: float|null, message_key: string, message_params: array<string, mixed>, action_key: string}>,
     *   metrics: array<string, array{level: string, value: float|null}>,
     *   primary_action_key: string
     * }
     */
    public static function evaluate(array $readings): array
    {
        $band = self::timeBand($readings['test_time'] ?? null);
        $issues = [];
        $metrics = [];

        $hasAnyTest = self::hasNumeric($readings, 'do_mg_l')
            || self::hasNumeric($readings, 'ph_level')
            || self::hasNumeric($readings, 'ammonia_mg_l')
            || self::hasNumeric($readings, 'nitrite_mg_l')
            || self::hasNumeric($readings, 'water_transparency_cm');

        if ($hasAnyTest && empty($readings['test_time'])) {
            $issues[] = self::makeIssue(
                'test_time',
                self::LEVEL_CAUTION,
                null,
                'waterAlertMissingTime',
                []
            );
        }

        foreach (['do_mg_l', 'ph_level', 'ammonia_mg_l', 'nitrite_mg_l', 'water_transparency_cm'] as $metric) {
            if (! self::hasNumeric($readings, $metric)) {
                $metrics[$metric] = ['level' => self::LEVEL_OK, 'value' => null];

                continue;
            }

            $value = (float) $readings[$metric];
            $result = match ($metric) {
                'do_mg_l' => self::evalDo($value, $band['key']),
                'ph_level' => self::evalPh($value, $band['key']),
                'ammonia_mg_l' => self::evalAmmonia($value, $band['key'], self::num($readings, 'ph_level')),
                'nitrite_mg_l' => self::evalNitrite($value, $band['key']),
                'water_transparency_cm' => self::evalTransparency($value, $band['key']),
            };

            $metrics[$metric] = ['level' => $result['level'], 'value' => $value];

            if ($result['level'] !== self::LEVEL_OK) {
                $issues[] = self::makeIssue(
                    $metric,
                    $result['level'],
                    $value,
                    $result['message_key'],
                    $result['message_params']
                );
            }
        }

        $level = self::LEVEL_OK;
        foreach ($issues as $issue) {
            if ($issue['level'] === self::LEVEL_DANGER) {
                $level = self::LEVEL_DANGER;
                break;
            }
            if ($issue['level'] === self::LEVEL_CAUTION) {
                $level = self::LEVEL_CAUTION;
            }
        }

        $primary = self::pickPrimaryIssue($issues);

        return [
            'level' => $level,
            'band' => $band,
            'issues' => $issues,
            'metrics' => $metrics,
            'primary_action_key' => $primary['action_key']
                ?? ($level === self::LEVEL_OK ? 'waterActionOk' : 'waterActionGeneric'),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{metric: string, level: string, value: float|null, message_key: string, message_params: array<string, mixed>, action_key: string}
     */
    private static function makeIssue(string $metric, string $level, ?float $value, string $messageKey, array $params): array
    {
        return [
            'metric' => $metric,
            'level' => $level,
            'value' => $value,
            'message_key' => $messageKey,
            'message_params' => $params,
            'action_key' => self::actionKey($metric, $level, array_merge($params, ['value' => $value])),
        ];
    }

    /**
     * @param  list<array{level: string, action_key: string}>  $issues
     * @return array{level: string, action_key: string}|null
     */
    private static function pickPrimaryIssue(array $issues): ?array
    {
        if ($issues === []) {
            return null;
        }

        $rank = [self::LEVEL_DANGER => 2, self::LEVEL_CAUTION => 1, self::LEVEL_OK => 0];
        usort($issues, fn ($a, $b) => ($rank[$b['level']] ?? 0) <=> ($rank[$a['level']] ?? 0));

        return $issues[0];
    }

    /**
     * Holatga qarab amaliy tavsiya kaliti.
     *
     * @param  array<string, mixed>  $ctx
     */
    private static function actionKey(string $metric, string $level, array $ctx): string
    {
        if ($metric === 'test_time') {
            return 'waterActionMissingTime';
        }

        $band = (string) ($ctx['band'] ?? 'unknown');
        $value = isset($ctx['value']) ? (float) $ctx['value'] : null;

        if ($metric === 'do_mg_l') {
            if ($level === self::LEVEL_DANGER) {
                return $band === 'morning' ? 'waterActionDoDangerMorning' : 'waterActionDoDanger';
            }

            return $band === 'morning' ? 'waterActionDoCautionMorning' : 'waterActionDoCaution';
        }

        if ($metric === 'ph_level') {
            $okTo = isset($ctx['ok_to']) ? (float) $ctx['ok_to'] : 8.0;
            $high = $value !== null && $value > $okTo;
            if ($level === self::LEVEL_DANGER) {
                return $high ? 'waterActionPhHighDanger' : 'waterActionPhLowDanger';
            }

            return $high ? 'waterActionPhHighCaution' : 'waterActionPhLowCaution';
        }

        if ($metric === 'ammonia_mg_l') {
            $highPh = ! empty($ctx['high_ph']);
            if ($highPh) {
                return $level === self::LEVEL_DANGER
                    ? 'waterActionAmmoniaHighPhDanger'
                    : 'waterActionAmmoniaHighPhCaution';
            }
            if ($band === 'evening') {
                return $level === self::LEVEL_DANGER
                    ? 'waterActionAmmoniaEveningDanger'
                    : 'waterActionAmmoniaEveningCaution';
            }

            return $level === self::LEVEL_DANGER
                ? 'waterActionAmmoniaDanger'
                : 'waterActionAmmoniaCaution';
        }

        if ($metric === 'nitrite_mg_l') {
            return $level === self::LEVEL_DANGER
                ? 'waterActionNitriteDanger'
                : 'waterActionNitriteCaution';
        }

        if ($metric === 'water_transparency_cm') {
            $okFrom = isset($ctx['ok_from']) ? (float) $ctx['ok_from'] : 25.0;
            $low = $value !== null && $value < $okFrom;
            if ($low) {
                return $level === self::LEVEL_DANGER
                    ? 'waterActionTransparencyLowDanger'
                    : 'waterActionTransparencyLowCaution';
            }

            return $level === self::LEVEL_DANGER
                ? 'waterActionTransparencyHighDanger'
                : 'waterActionTransparencyHighCaution';
        }

        return 'waterActionGeneric';
    }

    public static function evaluateReport(MainReport $report): array
    {
        $time = $report->test_time;
        if ($time instanceof Carbon) {
            $time = $time->format('H:i');
        } elseif (is_string($time) && strlen($time) >= 5) {
            $time = substr($time, 0, 5);
        }

        return self::evaluate([
            'test_time' => $time,
            'do_mg_l' => $report->do_mg_l,
            'ph_level' => $report->ph_level,
            'ammonia_mg_l' => $report->ammonia_mg_l,
            'nitrite_mg_l' => $report->nitrite_mg_l,
            'water_transparency_cm' => $report->water_transparency_cm,
        ]);
    }

    /** @return array{level: string, message_key: string, message_params: array<string, mixed>} */
    private static function evalDo(float $value, string $bandKey): array
    {
        $cfg = self::clientConfig()['metrics']['do_mg_l']['bands'][$bandKey]
            ?? self::clientConfig()['metrics']['do_mg_l']['bands']['unknown'];

        if ($value < $cfg['danger_below']) {
            return [
                'level' => self::LEVEL_DANGER,
                'message_key' => 'waterAlertDoDanger',
                'message_params' => [
                    'value' => $value,
                    'min' => $cfg['ok_min'],
                    'band' => $bandKey,
                ],
            ];
        }
        if ($value < $cfg['ok_min']) {
            return [
                'level' => self::LEVEL_CAUTION,
                'message_key' => 'waterAlertDoCaution',
                'message_params' => [
                    'value' => $value,
                    'min' => $cfg['ok_min'],
                    'band' => $bandKey,
                ],
            ];
        }

        return ['level' => self::LEVEL_OK, 'message_key' => '', 'message_params' => []];
    }

    /** @return array{level: string, message_key: string, message_params: array<string, mixed>} */
    private static function evalPh(float $value, string $bandKey): array
    {
        $cfg = self::clientConfig()['metrics']['ph_level']['bands'][$bandKey]
            ?? self::clientConfig()['metrics']['ph_level']['bands']['unknown'];

        if ($value < $cfg['danger_below'] || $value > $cfg['danger_above']) {
            return [
                'level' => self::LEVEL_DANGER,
                'message_key' => 'waterAlertPhDanger',
                'message_params' => [
                    'value' => $value,
                    'ok_from' => $cfg['ok'][0],
                    'ok_to' => $cfg['ok'][1],
                    'band' => $bandKey,
                ],
            ];
        }
        if ($value > $cfg['ok'][1] || $value < $cfg['ok'][0]) {
            return [
                'level' => self::LEVEL_CAUTION,
                'message_key' => 'waterAlertPhCaution',
                'message_params' => [
                    'value' => $value,
                    'ok_from' => $cfg['ok'][0],
                    'ok_to' => $cfg['ok'][1],
                    'band' => $bandKey,
                ],
            ];
        }

        return ['level' => self::LEVEL_OK, 'message_key' => '', 'message_params' => []];
    }

    /** @return array{level: string, message_key: string, message_params: array<string, mixed>} */
    private static function evalAmmonia(float $value, string $bandKey, ?float $ph): array
    {
        $cfg = self::clientConfig()['metrics']['ammonia_mg_l']['bands'][$bandKey]
            ?? self::clientConfig()['metrics']['ammonia_mg_l']['bands']['unknown'];

        $okMax = (float) $cfg['ok_max'];
        $cautionMax = (float) $cfg['caution_max'];

        // Yuqori pH da ammiak zaharliroq — chegarani qattiqlashtiramiz
        if ($ph !== null && $ph >= 8.5) {
            $okMax = min($okMax, 0.3);
            $cautionMax = min($cautionMax, 0.6);
        }

        if ($value > $cautionMax) {
            return [
                'level' => self::LEVEL_DANGER,
                'message_key' => 'waterAlertAmmoniaDanger',
                'message_params' => [
                    'value' => $value,
                    'max' => $okMax,
                    'band' => $bandKey,
                    'high_ph' => $ph !== null && $ph >= 8.5,
                ],
            ];
        }
        if ($value > $okMax) {
            return [
                'level' => self::LEVEL_CAUTION,
                'message_key' => 'waterAlertAmmoniaCaution',
                'message_params' => [
                    'value' => $value,
                    'max' => $okMax,
                    'band' => $bandKey,
                    'high_ph' => $ph !== null && $ph >= 8.5,
                ],
            ];
        }

        return ['level' => self::LEVEL_OK, 'message_key' => '', 'message_params' => []];
    }

    /** @return array{level: string, message_key: string, message_params: array<string, mixed>} */
    private static function evalNitrite(float $value, string $bandKey): array
    {
        $cfg = self::clientConfig()['metrics']['nitrite_mg_l']['bands'][$bandKey]
            ?? self::clientConfig()['metrics']['nitrite_mg_l']['bands']['unknown'];

        if ($value > (float) $cfg['caution_max']) {
            return [
                'level' => self::LEVEL_DANGER,
                'message_key' => 'waterAlertNitriteDanger',
                'message_params' => [
                    'value' => $value,
                    'max' => $cfg['ok_max'],
                    'band' => $bandKey,
                ],
            ];
        }
        if ($value > (float) $cfg['ok_max']) {
            return [
                'level' => self::LEVEL_CAUTION,
                'message_key' => 'waterAlertNitriteCaution',
                'message_params' => [
                    'value' => $value,
                    'max' => $cfg['ok_max'],
                    'band' => $bandKey,
                ],
            ];
        }

        return ['level' => self::LEVEL_OK, 'message_key' => '', 'message_params' => []];
    }

    /** @return array{level: string, message_key: string, message_params: array<string, mixed>} */
    private static function evalTransparency(float $value, string $bandKey): array
    {
        $cfg = self::clientConfig()['metrics']['water_transparency_cm']['bands'][$bandKey]
            ?? self::clientConfig()['metrics']['water_transparency_cm']['bands']['unknown'];

        [$okFrom, $okTo] = $cfg['ok'];

        if ($value < 15 || $value > 70) {
            return [
                'level' => self::LEVEL_DANGER,
                'message_key' => $value < 15 ? 'waterAlertTransparencyLowDanger' : 'waterAlertTransparencyHighDanger',
                'message_params' => [
                    'value' => $value,
                    'ok_from' => $okFrom,
                    'ok_to' => $okTo,
                    'band' => $bandKey,
                ],
            ];
        }
        if ($value < $okFrom || $value > $okTo) {
            return [
                'level' => self::LEVEL_CAUTION,
                'message_key' => $value < $okFrom ? 'waterAlertTransparencyLowCaution' : 'waterAlertTransparencyHighCaution',
                'message_params' => [
                    'value' => $value,
                    'ok_from' => $okFrom,
                    'ok_to' => $okTo,
                    'band' => $bandKey,
                ],
            ];
        }

        return ['level' => self::LEVEL_OK, 'message_key' => '', 'message_params' => []];
    }

    private static function minutesOfDay(?string $testTime): ?int
    {
        if ($testTime === null || $testTime === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $testTime, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return $h * 60 + $min;
    }

    private static function hasNumeric(array $readings, string $key): bool
    {
        if (! array_key_exists($key, $readings)) {
            return false;
        }
        $v = $readings[$key];

        return $v !== null && $v !== '';
    }

    private static function num(array $readings, string $key): ?float
    {
        if (! self::hasNumeric($readings, $key)) {
            return null;
        }

        return (float) $readings[$key];
    }
}
