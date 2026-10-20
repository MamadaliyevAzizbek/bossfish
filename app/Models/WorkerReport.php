<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'pond_id',
        'worker_id',
        'report_date',
        'work_done',
        'hours_worked',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'hours_worked' => 'decimal:2',
        ];
    }

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
