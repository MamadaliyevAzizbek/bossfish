<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmInvestment;
use App\Models\FinanceSection;
use App\Models\Pond;
use App\Services\FarmFinanceService;
use App\Services\FinanceWarehouseSync;
use App\Support\FarmAccess;
use App\Support\FinanceCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DirectorFinanceController extends Controller
{
    public function __construct(
        private readonly FinanceWarehouseSync $financeWarehouse,
        private readonly FarmFinanceService $farmFinance,
    ) {}

    public function overview(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $pondId = $request->filled('pond_id') ? (int) $request->query('pond_id') : null;

        return response()->json($this->farmFinance->overview($farm, $pondId));
    }

    public function sections(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $sections = $farm->financeSections()
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        return response()->json(['data' => $sections]);
    }

    public function storeSection(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('finance_sections', 'name')->where('farm_id', $farm->id),
            ],
        ]);

        $section = FinanceSection::create([
            'farm_id' => $farm->id,
            'name' => trim($data['name']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $section->id,
            'name' => $section->name,
            'category' => $section->categoryKey(),
        ], 201);
    }

    public function destroySection(Request $request, Farm $farm, FinanceSection $section): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureSectionBelongsToFarm($section, $farm);

        $hasExpenses = Expense::query()
            ->where('farm_id', $farm->id)
            ->where('category', $section->categoryKey())
            ->exists();

        if ($hasExpenses) {
            abort(422, 'Bu bo‘limda xarajatlar bor. Avval ularni boshqa bo‘limga ko‘chiring yoki o‘chiring.');
        }

        $section->delete();

        return response()->json(['message' => 'Bo‘lim o‘chirildi']);
    }

    public function expenses(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $category = $request->string('category')->toString();
        if ($category !== '' && ! $this->isValidCategoryForFarm($farm, $category)) {
            abort(422, 'Noto‘g‘ri xarajat bo‘limi');
        }

        $pondId = $request->filled('pond_id') ? (int) $request->query('pond_id') : null;
        if ($pondId) {
            $this->ensurePondBelongsToFarm($farm, $pondId);
        }

        $expenses = $this->farmFinance->expensesForPondFilter(
            $farm,
            $pondId,
            $category !== '' ? $category : null
        );

        if ($request->from) {
            $from = $request->from;
            $expenses = $expenses->filter(fn (Expense $e) => $e->expense_date >= $from);
        }
        if ($request->to) {
            $to = $request->to;
            $expenses = $expenses->filter(fn (Expense $e) => $e->expense_date <= $to);
        }

        $expenses = $expenses->values();

        $categoryTotal = $category !== ''
            ? round((float) $expenses->sum('amount'), 2)
            : null;

        return response()->json([
            'data' => $expenses,
            'category_total' => $categoryTotal,
            'pond_id' => $pondId,
        ]);
    }

    public function storeExpense(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $data = $this->validateExpense($request, $farm);

        $expense = Expense::create([
            ...$data,
            'farm_id' => $farm->id,
            'user_id' => $request->user()->id,
        ]);

        $this->financeWarehouse->apply($expense, $farm, $request->user());

        return response()->json($expense->fresh()->load(['user:id,name', 'pond:id,name']), 201);
    }

    public function updateExpense(Request $request, Farm $farm, Expense $expense): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureExpenseBelongsToFarm($expense, $farm);

        $data = $this->validateExpense($request, $farm, partial: true);
        $before = $expense->fresh();

        $expense->update($data);
        $this->financeWarehouse->resync($expense->fresh(), $farm, $request->user(), $before);

        return response()->json($expense->fresh()->load(['user:id,name', 'pond:id,name']));
    }

    public function destroyExpense(Request $request, Farm $farm, Expense $expense): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureExpenseBelongsToFarm($expense, $farm);

        $this->financeWarehouse->reverse($expense);
        $expense->delete();

        return response()->json(['message' => 'Xarajat o‘chirildi']);
    }

    public function investments(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $investments = FarmInvestment::query()
            ->with('user:id,name')
            ->where('farm_id', $farm->id)
            ->when($request->from, fn ($q) => $q->whereDate('invested_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('invested_at', '<=', $request->to))
            ->latest('invested_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $investments,
            'total' => (float) $investments->sum('amount'),
        ]);
    }

    public function storeInvestment(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $data = $request->validate([
            'investor' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0.01',
            'invested_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $investment = FarmInvestment::create([
            ...$data,
            'farm_id' => $farm->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($investment->load('user:id,name'), 201);
    }

    public function updateInvestment(Request $request, Farm $farm, FarmInvestment $investment): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureInvestmentBelongsToFarm($investment, $farm);

        $data = $request->validate([
            'investor' => 'sometimes|string|max:120',
            'amount' => 'sometimes|numeric|min:0.01',
            'invested_at' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        $investment->update($data);

        return response()->json($investment->fresh()->load('user:id,name'));
    }

    public function destroyInvestment(Request $request, Farm $farm, FarmInvestment $investment): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureInvestmentBelongsToFarm($investment, $farm);

        $investment->delete();

        return response()->json(['message' => 'Investitsiya o‘chirildi']);
    }

    private function validateExpense(Request $request, Farm $farm, bool $partial = false): array
    {
        $rules = [
            'category' => $partial ? 'sometimes|required|string|max:64' : 'required|string|max:64',
            'title' => ($partial ? 'sometimes' : 'required').'|string|max:255',
            'expense_date' => ($partial ? 'sometimes' : 'required').'|date',
            'quantity' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0.01',
            'description' => 'nullable|string',
            'pond_id' => 'nullable|integer',
            'scope' => 'nullable|in:farm,pond,all',
        ];

        $data = $request->validate($rules);

        $category = $data['category'] ?? $request->input('category');
        if ($category && ! $this->isValidCategoryForFarm($farm, $category)) {
            abort(422, 'Noto‘g‘ri xarajat bo‘limi');
        }

        $scope = $data['scope'] ?? null;
        unset($data['scope']);

        if ($scope === 'farm' || $scope === 'all') {
            $data['pond_id'] = null;
        } elseif (array_key_exists('pond_id', $data)) {
            if ($data['pond_id'] === null || $data['pond_id'] === '' || $data['pond_id'] === 'all') {
                $data['pond_id'] = null;
            } else {
                $pondId = (int) $data['pond_id'];
                $this->ensurePondOpenForExpense($farm, $pondId);
                $data['pond_id'] = $pondId;
            }
        } elseif (! $partial) {
            // Yangi yozuvda scope/pond_id majburiy emas — default butun xo‘jalik
            $data['pond_id'] = null;
        }

        $quantity = isset($data['quantity']) ? (float) $data['quantity'] : null;
        $unitPrice = isset($data['unit_price']) ? (float) $data['unit_price'] : null;
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

        if ($quantity !== null && $unitPrice !== null && $quantity > 0 && $unitPrice > 0) {
            $data['amount'] = round($quantity * $unitPrice, 2);
        } elseif ($amount !== null && $amount > 0) {
            $data['amount'] = round($amount, 2);
        } elseif (! $partial) {
            abort(422, 'Summa yoki miqdor × narx kiriting');
        }

        if (
            $category
            && FinanceCategories::syncsToWarehouse($category)
            && (! $partial || array_key_exists('quantity', $data))
        ) {
            $syncQuantity = isset($data['quantity']) ? (float) $data['quantity'] : 0;
            if ($syncQuantity <= 0) {
                abort(422, 'Yem va dori uchun miqdor (kg) kiriting — omborga avtomatik tushadi');
            }
        }

        if ($category && ! FinanceCategories::syncsToWarehouse($category)) {
            $data['quantity'] = $data['quantity'] ?? null;
            $data['unit_price'] = $data['unit_price'] ?? null;
        }

        return $data;
    }

    private function ensurePondBelongsToFarm(Farm $farm, int $pondId): void
    {
        $exists = Pond::query()
            ->whereKey($pondId)
            ->where('farm_id', $farm->id)
            ->exists();

        if (! $exists) {
            abort(422, 'Havza topilmadi yoki bu xo‘jalikka tegishli emas');
        }
    }

    private function ensurePondOpenForExpense(Farm $farm, int $pondId): void
    {
        $pond = Pond::query()
            ->whereKey($pondId)
            ->where('farm_id', $farm->id)
            ->first(['id', 'closed_at', 'status']);

        if (! $pond) {
            abort(422, 'Havza topilmadi yoki bu xo‘jalikka tegishli emas');
        }

        if ($pond->closed_at !== null || $pond->status === 'inactive' || $pond->status === 'closed') {
            abort(422, 'Yopilgan havzaga yangi xarajat kiritib bo‘lmaydi. Keyingi mavsumni oching yoki butun xo‘jalikni tanlang.');
        }
    }

    private function isValidCategoryForFarm(Farm $farm, string $category): bool
    {
        if (FinanceCategories::isFixed($category)) {
            return true;
        }

        if (! FinanceCategories::isCustom($category)) {
            return false;
        }

        $sectionId = FinanceCategories::customId($category);

        return $sectionId
            && $farm->financeSections()->whereKey($sectionId)->exists();
    }

    private function authorizeFarm(Request $request, Farm $farm): void
    {
        FarmAccess::authorizeDirectorOrOwner($request->user(), $farm);
    }

    private function ensureExpenseBelongsToFarm(Expense $expense, Farm $farm): void
    {
        if ((int) $expense->farm_id !== (int) $farm->id) {
            abort(404);
        }
    }

    private function ensureInvestmentBelongsToFarm(FarmInvestment $investment, Farm $farm): void
    {
        if ((int) $investment->farm_id !== (int) $farm->id) {
            abort(404);
        }
    }

    private function ensureSectionBelongsToFarm(FinanceSection $section, Farm $farm): void
    {
        if ((int) $section->farm_id !== (int) $farm->id) {
            abort(404);
        }
    }
}
