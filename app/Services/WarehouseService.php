<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\DailyReportItem;
use App\Models\Farm;
use App\Models\FeedType;
use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Support\FarmAccess;
use App\Support\WarehouseCategories;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    public const WHEAT_NAME = 'Bug\'doy';

    public const FERTILIZER_NAME = 'Amafos / Slitra';

    public function list(Farm $farm, ?string $category = null): Collection
    {
        $query = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->orderBy('category')
            ->orderBy('name');

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get();
    }

    public function feedOptions(Pond $pond, ?DailyReport $report = null): array
    {
        return collect($this->groupedOptionsForDailyReport($pond, $report)['feed'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'quantity' => $row['quantity'],
            ])
            ->values()
            ->all();
    }

    public function groupedOptionsForDailyReport(Pond $pond, ?DailyReport $report = null): array
    {
        $feed = [];
        $bran = [];
        $fertilizer = [];
        $medicines = [];

        foreach ($this->availableForDailyReport($pond, $report) as $item) {
            $row = $this->optionRow($item, $report);

            if ($item->category === WarehouseCategories::FEED) {
                if ($this->isBranOrWheatName($item->name)) {
                    $bran[] = $row;
                } else {
                    $feed[] = $row;
                }

                continue;
            }

            if ($item->category === WarehouseCategories::MEDICINE) {
                if ($this->isFertilizerName($item->name)) {
                    $fertilizer[] = $row;
                } else {
                    $medicines[] = $row;
                }
            }
        }

        return compact('feed', 'bran', 'fertilizer', 'medicines');
    }

    private function optionRow(WarehouseItem $item, ?DailyReport $report): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $this->availableQuantity($item, $report),
            'unit' => $item->unit,
            'category' => $item->category,
        ];
    }

    public function intakeFeedNames(Farm $farm): array
    {
        return collect($farm->feedTypes()->orderBy('name')->pluck('name'))
            ->merge(
                WarehouseItem::query()
                    ->where('farm_id', $farm->id)
                    ->where('category', WarehouseCategories::FEED)
                    ->orderBy('name')
                    ->pluck('name')
            )
            ->map(fn (string $name) => trim($name))
            ->filter(fn (string $name) => $name !== '' && ! $this->isBranName($name))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return array<int, WarehouseItem> */
    public function processIntake(Farm $farm, User $user, array $data): array
    {
        $feedKg = (float) ($data['feed_kg'] ?? 0);
        $wheatKg = (float) ($data['wheat_kg'] ?? 0);
        $fertilizerKg = (float) ($data['fertilizer_kg'] ?? 0);
        $medicineQty = (float) ($data['medicine_qty'] ?? 0);
        $feedName = trim((string) ($data['feed_name'] ?? ''));
        $medicineName = trim((string) ($data['medicine_name'] ?? ''));

        if ($feedKg > 0 || $wheatKg > 0 || $fertilizerKg > 0 || $medicineQty > 0 || $feedName !== '' || $medicineName !== '') {
            throw ValidationException::withMessages([
                'intake' => ['Yem va dori faqat Moliya bo‘limi orqali omborga tushadi. Uskuna kirimini davom ettiring.'],
            ]);
        }

        return [];
    }

    public function issueStock(Farm $farm, User $user, WarehouseItem $item, float $quantity): WarehouseItem
    {
        if ($item->category !== WarehouseCategories::EQUIPMENT) {
            throw ValidationException::withMessages([
                'category' => ['Faqat uskuna va texnika ombordan chiqim qilinishi mumkin.'],
            ]);
        }

        if ((int) $item->farm_id !== (int) $farm->id) {
            throw ValidationException::withMessages([
                'warehouse' => ['Mahsulot topilmadi.'],
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Miqdor 0 dan katta bo‘lishi kerak.'],
            ]);
        }

        if ($quantity > (float) $item->quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Omborda yetarli miqdor yo‘q (qoldiq: {$item->quantity})."],
            ]);
        }

        $item->quantity = round((float) $item->quantity - $quantity, 2);
        $item->save();

        return $item->fresh();
    }

    public function assertConsumableIntakeAllowed(string $category): void
    {
        if (in_array($category, WarehouseCategories::consumable(), true)) {
            throw ValidationException::withMessages([
                'category' => ['Yem va dori faqat Moliya bo‘limi orqali omborga tushadi.'],
            ]);
        }
    }

    public function availableForDailyReport(Pond $pond, ?DailyReport $report = null): Collection
    {
        $farm = FarmAccess::farmForPond($pond);

        $items = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->whereIn('category', WarehouseCategories::consumable())
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        if ($report) {
            $report->loadMissing('items');
            foreach ($report->items as $line) {
                $item = $items->get($line->warehouse_item_id);
                if ($item) {
                    $item->quantity = round((float) $item->quantity + (float) $line->quantity, 2);
                }
            }
        }

        return $items->values()
            ->filter(fn (WarehouseItem $item) => (float) $item->quantity > 0)
            ->values();
    }

    public function addStock(Farm $farm, User $user, array $data, ?UploadedFile $photo = null): WarehouseItem
    {
        $item = WarehouseItem::query()->firstOrNew([
            'farm_id' => $farm->id,
            'category' => $data['category'],
            'name' => trim($data['name']),
        ]);

        if (! $item->exists) {
            $item->unit = $data['unit'];
            $item->quantity = 0;
            $item->created_by = $user->id;
        } elseif ($item->unit !== $data['unit']) {
            throw ValidationException::withMessages([
                'unit' => ['Bu nom uchun boshqa o‘lchov birligi allaqachon ishlatilgan.'],
            ]);
        }

        if ($photo) {
            $this->replacePhoto($item, $photo, $farm);
        }

        $item->quantity = round((float) $item->quantity + (float) $data['quantity'], 2);
        $item->save();

        return $item->fresh();
    }

    private function replacePhoto(WarehouseItem $item, UploadedFile $photo, Farm $farm): void
    {
        if ($item->photo_path) {
            Storage::disk('public')->delete($item->photo_path);
        }

        $item->photo_path = $photo->store("warehouse/{$farm->id}", 'public');
    }

    public function restoreConsumption(DailyReport $report): void
    {
        $report->loadMissing('items.warehouseItem');

        foreach ($report->items as $line) {
            $item = $line->warehouseItem;
            if (! $item) {
                continue;
            }

            $item->quantity = round((float) $item->quantity + (float) $line->quantity, 2);
            $item->save();
        }
    }

    public function applyConsumption(DailyReport $report, array $lines): void
    {
        foreach ($lines as $line) {
            /** @var WarehouseItem $item */
            $item = $line['warehouse_item'];
            $quantity = (float) $line['quantity'];

            if ($quantity > (float) $item->quantity) {
                throw ValidationException::withMessages([
                    'items' => ["{$item->name} uchun omborda yetarli miqdor yo‘q (qoldiq: {$item->quantity})."],
                ]);
            }

            $item->quantity = round((float) $item->quantity - $quantity, 2);
            $item->save();

            DailyReportItem::create([
                'daily_report_id' => $report->id,
                'warehouse_item_id' => $item->id,
                'quantity' => $quantity,
            ]);
        }
    }

    public function resolveReportLines(Pond $pond, array $data, ?DailyReport $report = null): array
    {
        $resolved = $this->resolveLegacyReportLines($pond, $data, $report);

        if (! empty($data['medicine_items'])) {
            foreach ($this->resolveConsumableLines($pond, $data['medicine_items'], $report) as $line) {
                $resolved[] = $line;
            }
        }

        return $resolved;
    }

    public function resolveLegacyReportLines(Pond $pond, array $data, ?DailyReport $report = null): array
    {
        $farm = FarmAccess::farmForPond($pond);
        $resolved = [];
        $feedName = trim((string) ($data['feed_name'] ?? ''));
        $feedingKg = (float) ($data['feeding_kg'] ?? 0);
        $branKg = (float) ($data['wheat_bran_kg'] ?? 0);
        $fertilizerKg = (float) ($data['fertilizer_kg'] ?? 0);

        if ($feedingKg > 0) {
            $resolved[] = [
                'warehouse_item' => $this->findFeedItemByName($farm, $feedName, $feedingKg, $report),
                'quantity' => $feedingKg,
            ];
        }

        if ($branKg > 0) {
            $resolved[] = [
                'warehouse_item' => $this->resolveBranItem($farm, $data, $branKg, $report),
                'quantity' => $branKg,
            ];
        }

        if ($fertilizerKg > 0) {
            $resolved[] = [
                'warehouse_item' => $this->resolveFertilizerItem($farm, $data, $fertilizerKg, $report),
                'quantity' => $fertilizerKg,
            ];
        }

        $medicineItemId = (int) ($data['medicine_item_id'] ?? 0);
        $medicineQty = (float) ($data['medicine_qty'] ?? 0);
        if ($medicineItemId > 0 && $medicineQty > 0) {
            $resolved[] = [
                'warehouse_item' => $this->resolveMedicineItem($farm, $data, $medicineQty, $report),
                'quantity' => $medicineQty,
            ];
        }

        return $resolved;
    }

    private function resolveMedicineItem(Farm $farm, array $data, float $quantity, ?DailyReport $report): WarehouseItem
    {
        $item = $this->findItemById(
            $farm,
            (int) $data['medicine_item_id'],
            WarehouseCategories::MEDICINE,
            $quantity,
            $report
        );

        if ($this->isFertilizerName($item->name)) {
            throw ValidationException::withMessages([
                'medicine_item_id' => ['Amafos / Slitra uchun alohida maydon ishlating.'],
            ]);
        }

        return $item;
    }

    private function resolveBranItem(Farm $farm, array $data, float $quantity, ?DailyReport $report): WarehouseItem
    {
        if (! empty($data['wheat_item_id'])) {
            return $this->findItemById($farm, (int) $data['wheat_item_id'], WarehouseCategories::FEED, $quantity, $report);
        }

        return $this->findBranItem($farm, $quantity, $report);
    }

    private function resolveFertilizerItem(Farm $farm, array $data, float $quantity, ?DailyReport $report): WarehouseItem
    {
        if (! empty($data['fertilizer_item_id'])) {
            return $this->findItemById($farm, (int) $data['fertilizer_item_id'], WarehouseCategories::MEDICINE, $quantity, $report);
        }

        return $this->findMedicineItem($farm, $quantity, $report);
    }

    private function findItemById(
        Farm $farm,
        int $itemId,
        string $category,
        float $quantity,
        ?DailyReport $report
    ): WarehouseItem {
        $item = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('category', $category)
            ->whereKey($itemId)
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'warehouse' => ['Tanlangan mahsulot omborda topilmadi.'],
            ]);
        }

        $this->ensureAvailable($item, $quantity, $report, $item->name);

        return $item;
    }

    private function findFeedItemByName(Farm $farm, string $feedName, float $quantity, ?DailyReport $report): WarehouseItem
    {
        $item = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('category', WarehouseCategories::FEED)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($feedName))])
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'warehouse' => ["«{$feedName}» omborda yo‘q. Avval Moliya bo‘limidan yem xarajatini kiriting."],
            ]);
        }

        $this->ensureAvailable($item, $quantity, $report, $feedName);

        return $item;
    }

    private function findBranItem(Farm $farm, float $quantity, ?DailyReport $report): WarehouseItem
    {
        $items = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('category', WarehouseCategories::FEED)
            ->get()
            ->filter(fn (WarehouseItem $item) => $this->isBranOrWheatName($item->name))
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'warehouse' => ['Bug‘doy kepagi omborda yo‘q. Avval Moliya bo‘limidan yem xarajatini kiriting.'],
            ]);
        }

        return $this->pickItemWithStock($items, $quantity, $report, 'Bug‘doy kepagi');
    }

    private function findMedicineItem(Farm $farm, float $quantity, ?DailyReport $report): WarehouseItem
    {
        $items = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('category', WarehouseCategories::MEDICINE)
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'warehouse' => ['Amafos / Slitra omborda yo‘q. Avval Moliya bo‘limidan dori xarajatini kiriting.'],
            ]);
        }

        $preferred = $items->filter(
            fn (WarehouseItem $item) => $this->isFertilizerName($item->name)
        );

        return $this->pickItemWithStock(
            $preferred->isNotEmpty() ? $preferred->values() : $items,
            $quantity,
            $report,
            'Amafos / Slitra'
        );
    }

    private function pickItemWithStock(Collection $items, float $quantity, ?DailyReport $report, string $label): WarehouseItem
    {
        foreach ($items as $item) {
            try {
                $this->ensureAvailable($item, $quantity, $report, $item->name);

                return $item;
            } catch (ValidationException) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            'warehouse' => ["«{$label}» uchun omborda yetarli miqdor yo‘q. Avval Moliya bo‘limidan kiriting."],
        ]);
    }

    private function ensureAvailable(WarehouseItem $item, float $quantity, ?DailyReport $report, string $label): void
    {
        $available = $this->availableQuantity($item, $report);

        if ($quantity > $available) {
            $unit = $item->unit === 'piece' ? 'dona' : 'kg';

            throw ValidationException::withMessages([
                'warehouse' => ["«{$label}» uchun omborda yetarli miqdor yo‘q (qoldiq: {$available} {$unit}). Avval Moliya bo‘limidan kiriting."],
            ]);
        }
    }

    private function availableQuantity(WarehouseItem $item, ?DailyReport $report = null): float
    {
        $quantity = (float) $item->quantity;

        if ($report) {
            $report->loadMissing('items');
            foreach ($report->items as $line) {
                if ((int) $line->warehouse_item_id === (int) $item->id) {
                    $quantity += (float) $line->quantity;
                }
            }
        }

        return round($quantity, 2);
    }

    private function isBranName(string $name): bool
    {
        return (bool) preg_match('/kepak|\bun\b/u', mb_strtolower($name));
    }

    private function isBranOrWheatName(string $name): bool
    {
        return $this->isBranName($name)
            || mb_strtolower(trim($name)) === mb_strtolower(self::WHEAT_NAME);
    }

    private function isFertilizerName(string $name): bool
    {
        return (bool) preg_match('/amafos|slitra|selitra|ammophos/u', mb_strtolower($name));
    }

    public function resolveConsumableLines(Pond $pond, array $items, ?DailyReport $report = null): array
    {
        $farm = FarmAccess::farmForPond($pond);
        $resolved = [];

        foreach ($items as $row) {
            $item = WarehouseItem::query()
                ->where('farm_id', $farm->id)
                ->whereKey($row['warehouse_item_id'])
                ->first();

            if (! $item || ! in_array($item->category, WarehouseCategories::consumable(), true)) {
                throw ValidationException::withMessages([
                    'items' => ['Ombordan faqat yem va dori tanlash mumkin.'],
                ]);
            }

            if ($item->category === WarehouseCategories::MEDICINE && $this->isFertilizerName($item->name)) {
                throw ValidationException::withMessages([
                    'items' => ['Amafos / Slitra uchun alohida maydon ishlating.'],
                ]);
            }

            $quantity = (float) $row['quantity'];
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Miqdor 0 dan katta bo‘lishi kerak.'],
                ]);
            }

            $this->ensureAvailable($item, $quantity, $report, $item->name);

            $resolved[] = [
                'warehouse_item' => $item,
                'quantity' => $quantity,
            ];
        }

        return $resolved;
    }

    public function syncLegacyColumns(DailyReport $report, Collection $items): void
    {
        $feeding = 0.0;
        $bran = 0.0;
        $fertilizer = 0.0;
        $firstFeedName = null;

        foreach ($items as $line) {
            $item = $line->warehouseItem;
            $qty = (float) $line->quantity;
            $name = mb_strtolower($item->name);

            if ($item->category === WarehouseCategories::MEDICINE) {
                $fertilizer += $qty;
                continue;
            }

            if (preg_match('/kepak|bug.?doy|\bun\b/u', $name)) {
                $bran += $qty;
                continue;
            }

            $feeding += $qty;
            $firstFeedName ??= $item->name;
        }

        $report->update([
            'feed_name' => $firstFeedName ?? ($items->first()?->warehouseItem?->name ?? ''),
            'feeding_kg' => $feeding > 0 ? round($feeding, 2) : null,
            'wheat_bran_kg' => $bran > 0 ? round($bran, 2) : null,
            'fertilizer_kg' => $fertilizer > 0 ? round($fertilizer, 2) : null,
        ]);
    }

    public function medicineUsageSummary(Farm $farm, array $items): ?string
    {
        if ($items === []) {
            return null;
        }

        $parts = [];

        foreach ($items as $row) {
            $item = WarehouseItem::query()
                ->where('farm_id', $farm->id)
                ->find($row['warehouse_item_id'] ?? 0);

            if (! $item) {
                continue;
            }

            $unit = $item->unit === 'piece' ? 'dona' : 'kg';
            $qty = round((float) ($row['quantity'] ?? 0), 2);
            $parts[] = "{$item->name}: {$qty} {$unit}";
        }

        return $parts === [] ? null : implode('; ', $parts);
    }
}
