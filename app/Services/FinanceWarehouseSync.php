<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FeedType;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Support\FinanceCategories;
use App\Support\WarehouseCategories;
use Illuminate\Validation\ValidationException;

class FinanceWarehouseSync
{
    public function __construct(
        private readonly WarehouseService $warehouse,
    ) {}

    public function apply(Expense $expense, Farm $farm, User $user): void
    {
        if (! $this->isWarehouseCategory($expense->category)) {
            return;
        }

        $quantity = (float) ($expense->quantity ?? 0);
        if ($quantity <= 0) {
            return;
        }

        $warehouseCategory = $this->warehouseCategoryFor($expense->category);

        if ($expense->category === FinanceCategories::FEED) {
            FeedType::firstOrCreate(
                ['farm_id' => $farm->id, 'name' => trim($expense->title)],
                ['created_by' => $user->id]
            );
        }

        $item = $this->warehouse->addStock($farm, $user, [
            'category' => $warehouseCategory,
            'name' => trim($expense->title),
            'unit' => 'kg',
            'quantity' => $quantity,
        ]);

        $expense->warehouse_item_id = $item->id;
        $expense->saveQuietly();
    }

    public function reverse(Expense $expense): void
    {
        if (! $expense->warehouse_item_id) {
            return;
        }

        $quantity = (float) ($expense->quantity ?? 0);
        if ($quantity <= 0) {
            return;
        }

        $item = WarehouseItem::query()->find($expense->warehouse_item_id);
        if (! $item) {
            return;
        }

        $newQuantity = round((float) $item->quantity - $quantity, 2);
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'warehouse' => ['Bu xarajat ombordan allaqachon ishlatilgan. Avval ombordagi qoldiqni tekshiring.'],
            ]);
        }

        $item->quantity = $newQuantity;
        $item->save();
    }

    public function resync(Expense $expense, Farm $farm, User $user, Expense $before): void
    {
        $wasSynced = $this->isWarehouseCategory($before->category) && $before->warehouse_item_id;
        $shouldSync = $this->isWarehouseCategory($expense->category) && (float) ($expense->quantity ?? 0) > 0;

        if ($wasSynced) {
            $this->reverse($before);
            $expense->warehouse_item_id = null;
            $expense->saveQuietly();
        }

        if ($shouldSync) {
            $this->apply($expense, $farm, $user);
        }
    }

    private function isWarehouseCategory(string $category): bool
    {
        return FinanceCategories::syncsToWarehouse($category);
    }

    private function warehouseCategoryFor(string $category): string
    {
        return match ($category) {
            FinanceCategories::FEED => WarehouseCategories::FEED,
            FinanceCategories::MEDICINE => WarehouseCategories::MEDICINE,
            default => throw new \InvalidArgumentException("Unsupported category: {$category}"),
        };
    }
}
