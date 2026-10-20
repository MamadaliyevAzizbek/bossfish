<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PondClosureRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'pond_id',
        'requested_by',
        'status',
        'closed_on',
        'confirmations',
        'notes',
        'analysis_snapshot',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'pond_closure_id',
    ];

    protected function casts(): array
    {
        return [
            'closed_on' => 'date',
            'confirmations' => 'array',
            'analysis_snapshot' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(PondClosure::class, 'pond_closure_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
