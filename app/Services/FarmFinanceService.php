<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmInvestment;
use App\Models\FinanceSection;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Support\FinanceCategories;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FarmFinanceService
{
    /**
     * @return array{
     *   farm: array,
     *   categories: array<string, float>,
     *   custom_sections: list<array>,
     *   total_expenses: float,
     *   total_investments: float,
     *   total_sales: float,
     *   profit: float,
     *   pond_id: int|null,
     *   ponds: list<array>,
     *   by_pond: list<array>,
     *   feed_allocated: float
     * }
     */
    public function overview(Farm $farm, ?int $pondId = null): array
    {
        $ponds = $farm->ponds()
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'area_m2', 'closed_at']);

        $pondIds = $ponds->pluck('id');
        $feedUsageByPond = $this->feedUsageByPond($pondIds);
        $farmFeedKg = round((float) $feedUsageByPond->sum(), 3);

        $allExpenses = Expense::query()
            ->where('farm_id', $farm->id)
            ->get(['id', 'pond_id', 'category', 'amount', 'quantity']);

        $salesByPond = FishOperation::query()
            ->select('pond_id', DB::raw('SUM(amount) as total'))
            ->whereIn('pond_id', $pondIds)
            ->where('type', 'sale')
            ->groupBy('pond_id')
            ->pluck('total', 'pond_id');

        $byPond = $ponds->map(function (Pond $pond) use ($allExpenses, $salesByPond, $feedUsageByPond, $farmFeedKg) {
            $direct = round((float) $allExpenses
                ->where('pond_id', $pond->id)
                ->sum('amount'), 2);

            $feedShare = $this->allocatedFarmFeedAmount(
                $allExpenses,
                (float) ($feedUsageByPond[$pond->id] ?? 0),
                $farmFeedKg
            );

            $expenses = round($direct + $feedShare, 2);
            $sales = round((float) ($salesByPond[$pond->id] ?? 0), 2);

            return [
                'pond_id' => $pond->id,
                'name' => $pond->name,
                'status' => $pond->status,
                'total_expenses' => $expenses,
                'direct_expenses' => $direct,
                'feed_allocated' => $feedShare,
                'total_sales' => $sales,
                'profit' => round($sales - $expenses, 2),
                'feed_used_kg' => round((float) ($feedUsageByPond[$pond->id] ?? 0), 3),
            ];
        })->values()->all();

        if ($pondId) {
            $pond = $ponds->firstWhere('id', $pondId);
            if (! $pond) {
                abort(422, 'Havza topilmadi yoki bu xo‘jalikka tegishli emas');
            }

            $row = collect($byPond)->firstWhere('pond_id', $pondId);
            $categories = $this->categoriesForPond($allExpenses, $pondId, (float) ($feedUsageByPond[$pondId] ?? 0), $farmFeedKg);
            $sections = $this->customSections($farm, $categories);

            return [
                'farm' => $farm->only(['id', 'name', 'region']),
                'categories' => $categories,
                'custom_sections' => $sections,
                'total_expenses' => (float) ($row['total_expenses'] ?? 0),
                'total_investments' => (float) FarmInvestment::query()->where('farm_id', $farm->id)->sum('amount'),
                'total_sales' => (float) ($row['total_sales'] ?? 0),
                'profit' => (float) ($row['profit'] ?? 0),
                'pond_id' => $pondId,
                'ponds' => $ponds->map(fn (Pond $p) => $this->pondPayload($p))->values()->all(),
                'by_pond' => $byPond,
                'feed_allocated' => (float) ($row['feed_allocated'] ?? 0),
            ];
        }

        $byCategory = $allExpenses
            ->groupBy('category')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

        $categories = collect(FinanceCategories::EXPENSE)->mapWithKeys(
            fn (string $category) => [$category => (float) ($byCategory[$category] ?? 0)]
        )->all();

        // Custom categories present in expenses but not in fixed list
        foreach ($byCategory as $category => $total) {
            if (! array_key_exists($category, $categories)) {
                $categories[$category] = (float) $total;
            }
        }

        $sections = $this->customSections($farm, $categories);
        $totalExpenses = round((float) $allExpenses->sum('amount'), 2);
        $totalSales = round((float) $salesByPond->sum(), 2);

        return [
            'farm' => $farm->only(['id', 'name', 'region']),
            'categories' => $categories,
            'custom_sections' => $sections,
            'total_expenses' => $totalExpenses,
            'total_investments' => (float) FarmInvestment::query()->where('farm_id', $farm->id)->sum('amount'),
            'total_sales' => $totalSales,
            'profit' => round($totalSales - $totalExpenses, 2),
            'pond_id' => null,
            'ponds' => $ponds->map(fn (Pond $p) => $this->pondPayload($p))->values()->all(),
            'by_pond' => $byPond,
            'feed_allocated' => 0.0,
        ];
    }

    /** @return array{id: int, name: string, status: string|null, closed_at: string|null, is_closed: bool} */
    private function pondPayload(Pond $pond): array
    {
        $closed = $pond->closed_at !== null
            || $pond->status === 'inactive'
            || $pond->status === 'closed';

        return [
            'id' => $pond->id,
            'name' => $pond->name,
            'status' => $pond->status,
            'closed_at' => optional($pond->closed_at)?->toIso8601String(),
            'is_closed' => $closed,
        ];
    }

    /**
     * @param  Collection<int, Expense>  $allExpenses
     * @return array<string, float>
     */
    private function categoriesForPond(Collection $allExpenses, int $pondId, float $pondFeedKg, float $farmFeedKg): array
    {
        $direct = $allExpenses->where('pond_id', $pondId);
        $byCategory = $direct
            ->groupBy('category')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

        $feedShare = $this->allocatedFarmFeedAmount($allExpenses, $pondFeedKg, $farmFeedKg);
        $feedDirect = (float) ($byCategory[FinanceCategories::FEED] ?? 0);
        $byCategory[FinanceCategories::FEED] = round($feedDirect + $feedShare, 2);

        $categories = collect(FinanceCategories::EXPENSE)->mapWithKeys(
            fn (string $category) => [$category => (float) ($byCategory[$category] ?? 0)]
        )->all();

        foreach ($byCategory as $category => $total) {
            if (! array_key_exists($category, $categories)) {
                $categories[$category] = (float) $total;
            }
        }

        return $categories;
    }

    /**
     * @param  array<string, float>  $categories
     * @return list<array{id:int,name:string,category:string,total:float}>
     */
    private function customSections(Farm $farm, array $categories): array
    {
        return $farm->financeSections()
            ->orderBy('name')
            ->get()
            ->map(function (FinanceSection $section) use ($categories) {
                $key = $section->categoryKey();

                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'category' => $key,
                    'total' => (float) ($categories[$key] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int|string>  $pondIds
     * @return Collection<int|string, float>
     */
    public function feedUsageByPond(Collection $pondIds): Collection
    {
        if ($pondIds->isEmpty()) {
            return collect();
        }

        return DailyReport::query()
            ->select(
                'pond_id',
                DB::raw('SUM(COALESCE(feeding_kg, 0) + COALESCE(wheat_bran_kg, 0)) as total_kg')
            )
            ->whereIn('pond_id', $pondIds)
            ->groupBy('pond_id')
            ->pluck('total_kg', 'pond_id')
            ->map(fn ($v) => (float) $v);
    }

    /**
     * Farm-wide (pond_id null) feed purchases allocated by usage share.
     *
     * @param  Collection<int, Expense>  $allExpenses
     */
    public function allocatedFarmFeedAmount(Collection $allExpenses, float $pondFeedKg, float $farmFeedKg): float
    {
        $farmWideFeed = round((float) $allExpenses
            ->whereNull('pond_id')
            ->where('category', FinanceCategories::FEED)
            ->sum('amount'), 2);

        if ($farmWideFeed <= 0 || $farmFeedKg <= 0 || $pondFeedKg <= 0) {
            return 0.0;
        }

        return round($farmWideFeed * ($pondFeedKg / $farmFeedKg), 2);
    }

    /**
     * Effective expense amount for a pond filter (direct + allocated farm feed rows as virtual).
     *
     * @return Collection<int, array>
     */
    public function expensesForPondFilter(Farm $farm, ?int $pondId, ?string $category = null): Collection
    {
        $query = Expense::query()
            ->with(['user:id,name', 'pond:id,name'])
            ->where('farm_id', $farm->id);

        if ($category) {
            $query->where('category', $category);
        }

        if (! $pondId) {
            return $query->latest('expense_date')->latest('id')->get();
        }

        $pondIds = $farm->ponds()->pluck('id');
        $feedUsageByPond = $this->feedUsageByPond($pondIds);
        $farmFeedKg = round((float) $feedUsageByPond->sum(), 3);
        $pondFeedKg = (float) ($feedUsageByPond[$pondId] ?? 0);

        $direct = (clone $query)
            ->where('pond_id', $pondId)
            ->latest('expense_date')
            ->latest('id')
            ->get();

        $rows = $direct->map(fn (Expense $e) => $e);

        // Include farm-wide feed as allocated virtual rows when viewing a pond
        if (! $category || $category === FinanceCategories::FEED) {
            $farmWideFeed = Expense::query()
                ->with(['user:id,name'])
                ->where('farm_id', $farm->id)
                ->whereNull('pond_id')
                ->where('category', FinanceCategories::FEED)
                ->latest('expense_date')
                ->latest('id')
                ->get();

            foreach ($farmWideFeed as $expense) {
                $share = 0.0;
                if ($farmFeedKg > 0 && $pondFeedKg > 0) {
                    $share = round((float) $expense->amount * ($pondFeedKg / $farmFeedKg), 2);
                }
                if ($share <= 0) {
                    continue;
                }
                $clone = $expense->replicate();
                $clone->id = $expense->id;
                $clone->amount = $share;
                $clone->setAttribute('allocated_from_farm', true);
                $clone->setAttribute('original_amount', (float) $expense->amount);
                $clone->setRelation('user', $expense->user);
                $rows->push($clone);
            }
        }

        return $rows->sortByDesc(fn (Expense $e) => [$e->expense_date?->format('Y-m-d'), $e->id])->values();
    }
}
