<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use App\Support\FinanceCategories;
use App\Support\FishTypes;

class FishPurchaseExpenseSync
{
    public function sync(FishOperation $operation, Pond $pond, User $user, ?float $unitPrice): void
    {
        if ($operation->type !== 'add') {
            $this->deleteFor($operation);

            return;
        }

        $farmId = $pond->farm_id;
        if (! $farmId) {
            return;
        }

        $totalWeight = (float) ($operation->total_weight_kg ?? 0);
        $price = $unitPrice !== null ? (float) $unitPrice : null;

        if ($price === null || $price <= 0 || $totalWeight <= 0) {
            $this->deleteFor($operation);

            return;
        }

        $amount = round($totalWeight * $price, 2);
        $label = FishTypes::labels()[$operation->fish_type] ?? $operation->fish_type;
        $title = 'Baliq qo‘shish: '.$label;

        $payload = [
            'farm_id' => $farmId,
            'pond_id' => $pond->id,
            'user_id' => $user->id,
            'category' => FinanceCategories::OTHER,
            'amount' => $amount,
            'quantity' => $totalWeight,
            'unit_price' => $price,
            'expense_date' => $operation->operated_at,
            'title' => $title,
            'description' => $operation->quantity.' dona · '.$totalWeight.' kg × '.$price,
            'fish_operation_id' => $operation->id,
        ];

        $existing = Expense::query()->where('fish_operation_id', $operation->id)->first();
        if ($existing) {
            $existing->update($payload);
        } else {
            Expense::create($payload);
        }

        $operation->amount = $amount;
        $operation->saveQuietly();
    }

    public function deleteFor(FishOperation $operation): void
    {
        Expense::query()->where('fish_operation_id', $operation->id)->delete();

        if ($operation->type === 'add' && $operation->amount !== null) {
            $operation->amount = null;
            $operation->saveQuietly();
        }
    }
}
