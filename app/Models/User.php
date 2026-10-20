<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_DIRECTOR = 'director';
    public const ROLE_WORKER = 'worker';
    public const ROLE_VENGER = 'venger';

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_plain',
        'role',
        'phone',
        'is_active',
        'access_expires_at',
        'created_by',
        'director_id',
    ];

    protected $hidden = [
        'password',
        'password_plain',
        'remember_token',
    ];

    protected $appends = [
        'access_days_left',
        'access_expiring_soon',
        'access_expired',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_plain' => 'encrypted',
            'is_active' => 'boolean',
            'access_expires_at' => 'datetime',
        ];
    }

    public function getAccessDaysLeftAttribute(): ?int
    {
        if (! $this->access_expires_at) {
            return null;
        }

        $seconds = now()->diffInSeconds($this->access_expires_at, false);

        return (int) floor($seconds / 86400);
    }

    public function getAccessExpiredAttribute(): bool
    {
        return $this->access_expires_at !== null && $this->access_expires_at->isPast();
    }

    /** Kabinet muddati yaqin — 10 kun qolganda ogohlantirish. */
    public const ACCESS_EXPIRING_DAYS = 10;

    public function getAccessExpiringSoonAttribute(): bool
    {
        $left = $this->access_days_left;

        return $left !== null && $left >= 0 && $left <= self::ACCESS_EXPIRING_DAYS;
    }

    public function grantAccessDays(int $days, bool $extend = false): void
    {
        if ($extend && $this->access_expires_at && $this->access_expires_at->isFuture()) {
            $this->access_expires_at = $this->access_expires_at->copy()->addDays($days);
        } else {
            $this->access_expires_at = now()->addDays($days);
        }

        $this->save();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function workers(): HasMany
    {
        return $this->hasMany(User::class, 'director_id')->where('role', self::ROLE_WORKER);
    }

    public function directedFarms(): HasMany
    {
        return $this->hasMany(Farm::class, 'director_id');
    }

    public function clientSubscription(): HasOne
    {
        return $this->hasOne(ClientSubscription::class, 'director_id');
    }

    public function directedPonds(): HasMany
    {
        return $this->hasMany(Pond::class, 'director_id');
    }

    public function assignedFarms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_worker')
            ->withPivot(['position', 'hired_at'])
            ->withTimestamps();
    }

    public function assignedPonds(): BelongsToMany
    {
        return $this->belongsToMany(Pond::class, 'pond_worker')
            ->withPivot(['position', 'hired_at'])
            ->withTimestamps();
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isDirector(): bool
    {
        return $this->role === self::ROLE_DIRECTOR;
    }

    public function isWorker(): bool
    {
        return $this->role === self::ROLE_WORKER;
    }

    public function isVenger(): bool
    {
        return $this->role === self::ROLE_VENGER;
    }
}
