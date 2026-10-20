<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSubscription extends Model
{
    public const PLAN_CABINET = 'cabinet';
    public const PLAN_CONTROL = 'control';
    public const PLAN_INDIVIDUAL = 'individual';

    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ATTENTION = 'attention';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXPIRED = 'expired';

    public const PLANS = [
        self::PLAN_CABINET,
        self::PLAN_CONTROL,
        self::PLAN_INDIVIDUAL,
    ];

    public const PERIODS = [
        self::PERIOD_MONTH,
        self::PERIOD_YEAR,
    ];

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ATTENTION,
        self::STATUS_PAUSED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'director_id',
        'farm_id',
        'plan',
        'billing_period',
        'price_amount',
        'starts_at',
        'ends_at',
        'next_check_at',
        'check_interval_days',
        'last_checked_at',
        'status',
        'attention_note',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'next_check_at' => 'date',
            'last_checked_at' => 'datetime',
            'price_amount' => 'integer',
            'check_interval_days' => 'integer',
        ];
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function needsCheck(): bool
    {
        if (! in_array($this->plan, [self::PLAN_CONTROL, self::PLAN_INDIVIDUAL], true)) {
            return false;
        }

        if (! $this->next_check_at) {
            return true;
        }

        return $this->next_check_at->lte(now()->startOfDay());
    }

    public const EXPIRING_SOON_DAYS = 10;

    public function isExpiringSoon(?int $days = null): bool
    {
        if (! $this->ends_at) {
            return false;
        }

        $days ??= self::EXPIRING_SOON_DAYS;

        return $this->ends_at->lte(now()->addDays($days)->startOfDay())
            && $this->ends_at->gte(now()->startOfDay());
    }

    public function refreshExpiredStatus(): void
    {
        if ($this->ends_at && $this->ends_at->lt(now()->startOfDay()) && $this->status !== self::STATUS_PAUSED) {
            $this->status = self::STATUS_EXPIRED;
            $this->save();
        }
    }

    public function markChecked(?string $note = null): void
    {
        $interval = max(1, (int) ($this->check_interval_days ?: 7));
        $this->last_checked_at = now();
        $this->next_check_at = now()->addDays($interval)->toDateString();
        if ($note !== null && $note !== '') {
            $this->notes = trim(($this->notes ? $this->notes."\n" : '').$note);
        }
        if ($this->status === self::STATUS_ATTENTION) {
            $this->status = self::STATUS_ACTIVE;
            $this->attention_note = null;
        }
        $this->save();
    }

    public static function defaultPriceForPlan(string $plan, string $period = self::PERIOD_YEAR): ?int
    {
        if ($period === self::PERIOD_MONTH) {
            return match ($plan) {
                self::PLAN_CABINET => 135_000,
                self::PLAN_CONTROL => 450_000,
                default => null,
            };
        }

        return match ($plan) {
            self::PLAN_CABINET => 1_299_000,
            self::PLAN_CONTROL => 4_299_000,
            default => null,
        };
    }

    public static function periodDays(string $period): int
    {
        return $period === self::PERIOD_MONTH ? 30 : 365;
    }

    public static function defaultCheckInterval(string $plan): int
    {
        return match ($plan) {
            self::PLAN_CONTROL, self::PLAN_INDIVIDUAL => 7,
            default => 0,
        };
    }
}
