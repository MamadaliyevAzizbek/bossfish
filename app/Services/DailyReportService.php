<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    public function __construct(
        private readonly WarehouseService $warehouse,
    ) {}

    public function store(Pond $pond, User $user, array $data): DailyReport
    {
        $existing = DailyReport::query()
            ->where('pond_id', $pond->id)
            ->whereDate('report_date', $data['report_date'])
            ->first();

        $lines = $this->warehouse->resolveReportLines($pond, $data, $existing);

        return DB::transaction(function () use ($pond, $user, $data, $lines, $existing) {
            if ($existing) {
                $this->warehouse->restoreConsumption($existing);
                $existing->items()->delete();
                $existing->update($this->legacyAttributes($data, $user));
                $report = $existing;
            } else {
                $report = DailyReport::create([
                    'pond_id' => $pond->id,
                    ...$this->legacyAttributes($data, $user),
                ]);
            }

            $this->warehouse->applyConsumption($report, $lines);

            return $report->fresh(['user:id,name', 'items.warehouseItem']);
        });
    }

    public function update(DailyReport $report, Pond $pond, User $user, array $data): DailyReport
    {
        $lines = $this->warehouse->resolveReportLines($pond, $data, $report);

        return DB::transaction(function () use ($report, $user, $data, $lines) {
            $this->warehouse->restoreConsumption($report);
            $report->items()->delete();

            $report->update($this->legacyAttributes($data, $user));

            $this->warehouse->applyConsumption($report, $lines);

            return $report->fresh(['user:id,name', 'items.warehouseItem']);
        });
    }

    public function destroy(DailyReport $report): void
    {
        DB::transaction(function () use ($report) {
            $this->warehouse->restoreConsumption($report);
            $report->delete();
        });
    }

    private function legacyAttributes(array $data, User $user): array
    {
        $feedName = trim((string) ($data['feed_name'] ?? ''));

        return [
            'report_date' => $data['report_date'],
            'user_id' => $user->id,
            'feed_name' => $feedName !== '' ? $feedName : null,
            'feeding_kg' => $data['feeding_kg'] ?? null,
            'wheat_bran_kg' => $data['wheat_bran_kg'] ?? null,
            'fertilizer_kg' => $data['fertilizer_kg'] ?? null,
        ];
    }
}
