<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pond extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'name',
        'region',
        'district',
        'latitude',
        'longitude',
        'area_m2',
        'current_stock',
        'director_id',
        'created_by',
        'status',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'current_stock' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pond_worker')
            ->withPivot(['position', 'hired_at'])
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function mainReports(): HasMany
    {
        return $this->hasMany(MainReport::class);
    }


    public function workerReports(): HasMany
    {
        return $this->hasMany(WorkerReport::class);
    }


    public function fishOperations(): HasMany
    {
        return $this->hasMany(FishOperation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function closure(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PondClosure::class)->latestOfMany();
    }

    public function closureRequests(): HasMany
    {
        return $this->hasMany(PondClosureRequest::class);
    }

    public function pendingClosureRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PondClosureRequest::class)
            ->where('status', PondClosureRequest::STATUS_PENDING)
            ->latestOfMany();
    }

}
