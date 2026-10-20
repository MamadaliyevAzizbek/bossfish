<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DailyReport;
use App\Models\Farm;
use App\Models\FeedType;
use App\Models\FishOperation;
use App\Models\MainReport;
use App\Models\Pond;
use App\Models\PondClosure;
use App\Models\PondClosureRequest;
use App\Models\PondCreateRequest;
use App\Models\WarehouseItem;
use App\Services\DailyReportService;
use App\Services\FishPurchaseExpenseSync;
use App\Services\PondClosureService;
use App\Services\WarehouseService;
use App\Services\WaterQualityStandards;
use App\Services\WorkScheduleReminders;
use App\Support\Districts;
use App\Support\FarmAccess;
use App\Support\FishTypes;
use App\Support\OwnerAccess;
use App\Support\PondAccess;
use App\Support\Regions;
use App\Support\WarehouseCategories;
use App\Support\WarehouseUnits;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DirectorCabinetController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouse,
        private readonly DailyReportService $dailyReports,
        private readonly PondClosureService $pondClosure,
        private readonly FishPurchaseExpenseSync $fishPurchaseExpense,
    ) {}

    public function regions(): JsonResponse
    {
        $regions = collect(Regions::all())->map(function (string $name) {
            $coords = Regions::latLng($name);

            return [
                'name' => $name,
                'lat' => $coords['lat'] ?? null,
                'lng' => $coords['lng'] ?? null,
            ];
        })->values();

        return response()->json($regions);
    }

    public function farms(Request $request): JsonResponse
    {
        $farms = Farm::query()
            ->withCount('ponds')
            ->when($request->user()->isOwner(), function ($query) use ($request) {
                $directorId = OwnerAccess::actingDirectorId($request);
                if ($directorId) {
                    $query->where('director_id', $directorId);
                } else {
                    $query->whereRaw('0 = 1');
                }
            }, function ($query) use ($request) {
                $query->where('director_id', $request->user()->id);
            })
            ->latest()
            ->get();

        return response()->json($farms);
    }

    public function showFarm(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $farm->loadCount('ponds');

        return response()->json($farm);
    }

    public function farmPonds(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        return response()->json(
            $farm->ponds()->latest()->get()
        );
    }

    public function farmPondCreateRequests(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $requests = PondCreateRequest::query()
            ->where('farm_id', $farm->id)
            ->with(['requester:id,name,phone', 'reviewer:id,name', 'pond:id,name'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (PondCreateRequest $item) => $this->formatPondCreateRequest($item));

        return response()->json(['requests' => $requests]);
    }

    public function storeFarmPond(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        if ($request->user()->isOwner()) {
            return $this->createPondDirectly($request, $farm);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'region' => ['required', 'string', Rule::in(Regions::all())],
            'district' => 'required|string|max:120',
            'area_m2' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! Districts::isValid($data['region'], $data['district'])) {
            throw ValidationException::withMessages([
                'district' => ['Tanlangan tuman/shahar ushbu viloyatga tegishli emas.'],
            ]);
        }

        $pendingExists = PondCreateRequest::query()
            ->where('farm_id', $farm->id)
            ->where('requested_by', $request->user()->id)
            ->where('status', PondCreateRequest::STATUS_PENDING)
            ->where('name', $data['name'])
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'name' => ['Shu nomdagi havza uchun kutilayotgan ariza mavjud. BOSS javobini kuting.'],
            ]);
        }

        $createRequest = PondCreateRequest::create([
            'farm_id' => $farm->id,
            'requested_by' => $request->user()->id,
            'status' => PondCreateRequest::STATUS_PENDING,
            'name' => $data['name'],
            'region' => $data['region'],
            'district' => $data['district'],
            'area_m2' => $data['area_m2'],
            'notes' => $data['notes'] ?? null,
        ]);

        $createRequest->load(['farm:id,name,region', 'requester:id,name,phone']);

        return response()->json([
            'message' => 'Havza ochish arizasi BOSS kabinetiga yuborildi',
            'request' => $this->formatPondCreateRequest($createRequest),
        ], 201);
    }

    private function createPondDirectly(Request $request, Farm $farm): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'region' => ['required', 'string', Rule::in(Regions::all())],
            'district' => 'required|string|max:120',
            'area_m2' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! Districts::isValid($data['region'], $data['district'])) {
            throw ValidationException::withMessages([
                'district' => ['Tanlangan tuman/shahar ushbu viloyatga tegishli emas.'],
            ]);
        }

        $pond = Pond::create([
            'farm_id' => $farm->id,
            'name' => $data['name'],
            'region' => $data['region'],
            'district' => $data['district'],
            'latitude' => null,
            'longitude' => null,
            'area_m2' => $data['area_m2'],
            'current_stock' => 0,
            'director_id' => $farm->director_id,
            'created_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ]);

        $this->logActivity(
            $pond,
            $request->user()->id,
            'pond_created',
            'Havza qo‘shildi',
            $pond->name.' havzasi BOSS tomonidan yaratildi',
            [
                'farm_id' => $farm->id,
                'region' => $pond->region,
                'district' => $pond->district,
                'area_m2' => $pond->area_m2,
            ]
        );

        return response()->json($pond, 201);
    }

    private function formatPondCreateRequest(PondCreateRequest $item): array
    {
        return [
            'id' => $item->id,
            'status' => $item->status,
            'name' => $item->name,
            'region' => $item->region,
            'district' => $item->district,
            'area_m2' => $item->area_m2 !== null ? (float) $item->area_m2 : null,
            'notes' => $item->notes,
            'review_notes' => $item->review_notes,
            'reviewed_at' => optional($item->reviewed_at)->toIso8601String(),
            'created_at' => optional($item->created_at)->toIso8601String(),
            'requester' => $item->requester ? $item->requester->only(['id', 'name', 'phone']) : null,
            'reviewer' => $item->reviewer ? $item->reviewer->only(['id', 'name']) : null,
            'farm' => $item->farm ? $item->farm->only(['id', 'name', 'region']) : null,
            'pond' => $item->pond ? $item->pond->only(['id', 'name']) : null,
        ];
    }

    public function ponds(Request $request): JsonResponse
    {
        $ponds = Pond::query()
            ->when($request->user()->isOwner(), function ($query) use ($request) {
                $directorId = OwnerAccess::actingDirectorId($request);
                if ($directorId) {
                    $query->where('director_id', $directorId);
                } else {
                    $query->whereRaw('0 = 1');
                }
            }, function ($query) use ($request) {
                $query->where('director_id', $request->user()->id);
            })
            ->latest()
            ->get();

        return response()->json($ponds);
    }

    public function storePond(Request $request): JsonResponse
    {
        abort(422, 'Havzani hojalik ichida qo‘shing.');
    }

    public function showPond(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $pond->loadCount(['dailyReports', 'fishOperations']);
        $pond->load('pendingClosureRequest');

        return response()->json([
            ...$pond->toArray(),
            'has_pending_closure_request' => $pond->pendingClosureRequest !== null,
        ]);
    }

    public function dailyReports(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $reports = $pond->dailyReports()
            ->with(['user:id,name', 'items.warehouseItem'])
            ->latest('report_date')
            ->paginate(30);

        return response()->json($reports);
    }

    public function warehouse(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $category = $request->query('category');
        if ($category && ! in_array($category, WarehouseCategories::all(), true)) {
            abort(422, 'Noto‘g‘ri kategoriya');
        }

        return response()->json($this->warehouse->list($farm, $category));
    }

    public function warehouseAvailable(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $report = null;
        if ($request->filled('daily_report_id')) {
            $report = DailyReport::query()
                ->where('pond_id', $pond->id)
                ->whereKey($request->query('daily_report_id'))
                ->firstOrFail();
        }

        return response()->json($this->warehouse->groupedOptionsForDailyReport($pond, $report));
    }

    public function storeWarehouseItem(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $data = $request->validate([
            'category' => ['required', Rule::in(WarehouseCategories::all())],
            'name' => 'required|string|max:120',
            'unit' => ['required', Rule::in(WarehouseUnits::all())],
            'quantity' => 'required|numeric|min:0.01',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $this->warehouse->assertConsumableIntakeAllowed($data['category']);

        $item = $this->warehouse->addStock($farm, $request->user(), $data, $request->file('photo'));

        $this->logFarmWarehouseActivity(
            $farm,
            $request->user()->id,
            'warehouse_in',
            'Omborga qo‘shildi',
            "{$item->name} — {$data['quantity']} {$item->unit}",
            [
                'category' => $item->category,
                'name' => $item->name,
                'quantity' => $data['quantity'],
                'unit' => $item->unit,
            ]
        );

        return response()->json($item, 201);
    }

    public function issueWarehouseItem(Request $request, Farm $farm, WarehouseItem $warehouseItem): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        if ((int) $warehouseItem->farm_id !== (int) $farm->id) {
            abort(404);
        }

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $item = $this->warehouse->issueStock($farm, $request->user(), $warehouseItem, (float) $data['quantity']);

        $this->logFarmWarehouseActivity(
            $farm,
            $request->user()->id,
            'warehouse_out',
            'Ombordan chiqim qilindi',
            "{$item->name} — {$data['quantity']} {$item->unit}",
            [
                'category' => $item->category,
                'name' => $item->name,
                'quantity' => $data['quantity'],
                'unit' => $item->unit,
            ]
        );

        return response()->json($item);
    }

    public function feedTypes(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $report = null;
        if ($request->filled('daily_report_id')) {
            $report = DailyReport::query()
                ->where('pond_id', $pond->id)
                ->whereKey($request->query('daily_report_id'))
                ->firstOrFail();
        }

        return response()->json($this->warehouse->feedOptions($pond, $report));
    }

    public function warehouseIntakeFeedNames(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        return response()->json($this->warehouse->intakeFeedNames($farm));
    }

    public function storeWarehouseIntake(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $data = $request->validate([
            'feed_name' => 'nullable|string|max:120',
            'feed_kg' => 'nullable|numeric|min:0',
            'medicine_name' => 'nullable|string|max:120',
            'medicine_unit' => 'nullable|in:kg,piece',
            'medicine_qty' => 'nullable|numeric|min:0',
            'wheat_kg' => 'nullable|numeric|min:0',
            'fertilizer_kg' => 'nullable|numeric|min:0',
        ]);

        $items = $this->warehouse->processIntake($farm, $request->user(), $data);

        $feedName = trim((string) ($data['feed_name'] ?? ''));
        $feedKg = (float) ($data['feed_kg'] ?? 0);
        $wheatKg = (float) ($data['wheat_kg'] ?? 0);
        $fertilizerKg = (float) ($data['fertilizer_kg'] ?? 0);
        $medicineName = trim((string) ($data['medicine_name'] ?? ''));
        $medicineQty = (float) ($data['medicine_qty'] ?? 0);
        $medicineUnit = (string) ($data['medicine_unit'] ?? 'kg');

        if ($feedKg > 0 && $feedName !== '') {
            $this->logFarmWarehouseActivity($farm, $request->user()->id, 'warehouse_in', 'Omborga kirim qilindi', "{$feedName} — {$feedKg} kg", [
                'category' => WarehouseCategories::FEED,
                'name' => $feedName,
                'quantity' => $feedKg,
                'unit' => 'kg',
            ]);
        }

        if ($wheatKg > 0) {
            $this->logFarmWarehouseActivity($farm, $request->user()->id, 'warehouse_in', 'Omborga kirim qilindi', WarehouseService::WHEAT_NAME." — {$wheatKg} kg", [
                'category' => WarehouseCategories::FEED,
                'name' => WarehouseService::WHEAT_NAME,
                'quantity' => $wheatKg,
                'unit' => 'kg',
            ]);
        }

        if ($fertilizerKg > 0) {
            $this->logFarmWarehouseActivity($farm, $request->user()->id, 'warehouse_in', 'Omborga kirim qilindi', WarehouseService::FERTILIZER_NAME." — {$fertilizerKg} kg", [
                'category' => WarehouseCategories::MEDICINE,
                'name' => WarehouseService::FERTILIZER_NAME,
                'quantity' => $fertilizerKg,
                'unit' => 'kg',
            ]);
        }

        if ($medicineQty > 0 && $medicineName !== '') {
            $unitLabel = $medicineUnit === 'piece' ? 'dona' : 'kg';
            $this->logFarmWarehouseActivity($farm, $request->user()->id, 'warehouse_in', 'Omborga kirim qilindi', "{$medicineName} — {$medicineQty} {$unitLabel}", [
                'category' => WarehouseCategories::MEDICINE,
                'name' => $medicineName,
                'quantity' => $medicineQty,
                'unit' => $medicineUnit,
            ]);
        }

        return response()->json([
            'message' => $items === [] ? 'Saqlangan' : 'Kirim qilindi',
            'items' => $items,
        ], $items === [] ? 200 : 201);
    }

    public function storeDailyReport(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);

        $request->merge([
            'feed_name' => trim((string) $request->input('feed_name', '')),
        ]);

        $data = $request->validate([
            'report_date' => 'required|date',
            'feed_name' => 'nullable|string|max:100|not_in:__new__',
            'feeding_kg' => 'nullable|numeric|min:0.01',
            'wheat_item_id' => 'nullable|integer',
            'wheat_bran_kg' => 'nullable|numeric|min:0.01',
            'fertilizer_item_id' => 'nullable|integer',
            'fertilizer_kg' => 'nullable|numeric|min:0.01',
            'medicine_item_id' => 'nullable|integer',
            'medicine_qty' => 'nullable|numeric|min:0.01',
            'medicine_items' => 'nullable|array',
            'medicine_items.*.warehouse_item_id' => 'required|integer',
            'medicine_items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $data = $this->normalizeDailyReportPayload($data);

        $farm = FarmAccess::farmForPond($pond);

        if (! empty($data['feed_name'])) {
            FeedType::firstOrCreate(
                ['farm_id' => $farm->id, 'name' => $data['feed_name']],
                ['created_by' => $request->user()->id]
            );
        }

        $report = $this->dailyReports->store($pond, $request->user(), $data);

        $this->logActivity(
            $pond,
            $request->user()->id,
            'daily_report',
            'Kunlik hisobot topshirildi',
            $data['report_date'].' sanasi uchun kunlik hisobot saqlandi',
            array_filter([
                'feed_name' => $data['feed_name'] ?? null,
                'feeding_kg' => $data['feeding_kg'] ?? null,
                'wheat_bran_kg' => $data['wheat_bran_kg'] ?? null,
                'fertilizer_kg' => $data['fertilizer_kg'] ?? null,
                'medicine_usage' => $this->warehouse->medicineUsageSummary($farm, $this->normalizedMedicineItems($data)),
            ], fn ($value) => $value !== null && $value !== ''),
            $data['report_date']
        );

        return response()->json($report, 201);
    }

    public function updateDailyReport(Request $request, Pond $pond, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);
        $this->ensureBelongsToPond($pond, $dailyReport->pond_id);

        $request->merge([
            'feed_name' => trim((string) $request->input('feed_name', '')),
        ]);

        $data = $request->validate([
            'report_date' => [
                'required',
                'date',
                Rule::unique('daily_reports', 'report_date')
                    ->where(fn ($q) => $q->where('pond_id', $pond->id))
                    ->ignore($dailyReport->id),
            ],
            'feed_name' => 'nullable|string|max:100|not_in:__new__',
            'feeding_kg' => 'nullable|numeric|min:0.01',
            'wheat_item_id' => 'nullable|integer',
            'wheat_bran_kg' => 'nullable|numeric|min:0.01',
            'fertilizer_item_id' => 'nullable|integer',
            'fertilizer_kg' => 'nullable|numeric|min:0.01',
            'medicine_item_id' => 'nullable|integer',
            'medicine_qty' => 'nullable|numeric|min:0.01',
            'medicine_items' => 'nullable|array',
            'medicine_items.*.warehouse_item_id' => 'required|integer',
            'medicine_items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $data = $this->normalizeDailyReportPayload($data);

        $farm = FarmAccess::farmForPond($pond);

        if (! empty($data['feed_name'])) {
            FeedType::firstOrCreate(
                ['farm_id' => $farm->id, 'name' => $data['feed_name']],
                ['created_by' => $request->user()->id]
            );
        }

        $report = $this->dailyReports->update($dailyReport, $pond, $request->user(), $data);

        $this->logActivity(
            $pond,
            $request->user()->id,
            'daily_report_updated',
            'Kunlik hisobot o‘zgartirildi',
            $data['report_date'].' sanasi uchun kunlik hisobot yangilandi',
            array_filter([
                'feed_name' => $data['feed_name'] ?? null,
                'feeding_kg' => $data['feeding_kg'] ?? null,
                'wheat_bran_kg' => $data['wheat_bran_kg'] ?? null,
                'fertilizer_kg' => $data['fertilizer_kg'] ?? null,
                'medicine_usage' => $this->warehouse->medicineUsageSummary($farm, $this->normalizedMedicineItems($data)),
            ], fn ($value) => $value !== null && $value !== ''),
            $data['report_date']
        );

        return response()->json($report);
    }

    private function normalizeDailyReportPayload(array $data): array
    {
        $feedName = trim((string) ($data['feed_name'] ?? ''));
        $feedingKg = array_key_exists('feeding_kg', $data) && $data['feeding_kg'] !== null && $data['feeding_kg'] !== ''
            ? (float) $data['feeding_kg']
            : null;

        if ($feedName === '') {
            $feedName = null;
            $feedingKg = null;
        } elseif ($feedingKg === null || $feedingKg <= 0) {
            throw ValidationException::withMessages([
                'feeding_kg' => ['Yem tanlanganda miqdor majburiy.'],
            ]);
        }

        $wheatItemId = ! empty($data['wheat_item_id']) ? (int) $data['wheat_item_id'] : null;
        $wheatBranKg = array_key_exists('wheat_bran_kg', $data) && $data['wheat_bran_kg'] !== null && $data['wheat_bran_kg'] !== ''
            ? (float) $data['wheat_bran_kg']
            : null;
        if ($wheatBranKg === null || $wheatBranKg <= 0) {
            $wheatItemId = null;
            $wheatBranKg = null;
        }

        $fertilizerItemId = ! empty($data['fertilizer_item_id']) ? (int) $data['fertilizer_item_id'] : null;
        $fertilizerKg = array_key_exists('fertilizer_kg', $data) && $data['fertilizer_kg'] !== null && $data['fertilizer_kg'] !== ''
            ? (float) $data['fertilizer_kg']
            : null;
        if ($fertilizerKg === null || $fertilizerKg <= 0) {
            $fertilizerItemId = null;
            $fertilizerKg = null;
        }

        $medicineItemId = ! empty($data['medicine_item_id']) ? (int) $data['medicine_item_id'] : null;
        $medicineQty = array_key_exists('medicine_qty', $data) && $data['medicine_qty'] !== null && $data['medicine_qty'] !== ''
            ? (float) $data['medicine_qty']
            : null;
        if ($medicineQty === null || $medicineQty <= 0) {
            $medicineItemId = null;
            $medicineQty = null;
        } elseif (! $medicineItemId) {
            throw ValidationException::withMessages([
                'medicine_item_id' => ['Dori tanlanishi shart.'],
            ]);
        }

        $medicineItems = collect($data['medicine_items'] ?? [])
            ->filter(fn ($row) => ! empty($row['warehouse_item_id']) && (float) ($row['quantity'] ?? 0) > 0)
            ->values()
            ->all();

        $hasFeed = $feedName !== null && $feedingKg !== null && $feedingKg > 0;
        $hasBran = $wheatBranKg !== null && $wheatBranKg > 0;
        $hasFertilizer = $fertilizerKg !== null && $fertilizerKg > 0;
        $hasMedicine = ($medicineItemId !== null && $medicineQty !== null && $medicineQty > 0) || $medicineItems !== [];

        if (! $hasFeed && ! $hasBran && ! $hasFertilizer && ! $hasMedicine) {
            throw ValidationException::withMessages([
                'report' => ['Yem, kepak, o‘g‘it yoki doridan kamida bittasini kiriting.'],
            ]);
        }

        $data['feed_name'] = $feedName;
        $data['feeding_kg'] = $feedingKg;
        $data['wheat_item_id'] = $wheatItemId;
        $data['wheat_bran_kg'] = $wheatBranKg;
        $data['fertilizer_item_id'] = $fertilizerItemId;
        $data['fertilizer_kg'] = $fertilizerKg;
        $data['medicine_item_id'] = $medicineItemId;
        $data['medicine_qty'] = $medicineQty;
        $data['medicine_items'] = $medicineItems;

        return $data;
    }

    private function normalizedMedicineItems(array $data): array
    {
        $items = $data['medicine_items'] ?? [];

        if (! empty($data['medicine_item_id']) && ! empty($data['medicine_qty'])) {
            $items[] = [
                'warehouse_item_id' => (int) $data['medicine_item_id'],
                'quantity' => (float) $data['medicine_qty'],
            ];
        }

        return $items;
    }

    public function destroyDailyReport(Request $request, Pond $pond, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);
        $this->ensureBelongsToPond($pond, $dailyReport->pond_id);

        $date = optional($dailyReport->report_date)->format('Y-m-d');
        $this->dailyReports->destroy($dailyReport);

        $this->logActivity(
            $pond,
            $request->user()->id,
            'daily_report_deleted',
            'Kunlik hisobot o‘chirildi',
            $date.' sanasidagi kunlik hisobot o‘chirildi',
            ['report_date' => $date],
            $date
        );

        return response()->json(['message' => 'Kunlik hisobot o‘chirildi']);
    }

    public function activities(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $activities = $pond->activityLogs()
            ->with('user:id,name,phone,role')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($activities);
    }

    public function summary(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $labels = FishTypes::labels();
        $currentWeights = $this->suggestedFishWeights($pond);
        $initialWeights = $this->initialFishWeights($pond);
        $stockByType = $this->stockByFishType($pond);

        $fish = [];
        foreach (FishTypes::all() as $type) {
            $count = (int) ($stockByType[$type]['count'] ?? 0);
            if ($count <= 0 && (int) ($stockByType[$type]['added'] ?? 0) === 0) {
                continue;
            }
            if ($count < 0) {
                $count = 0;
            }

            $currentWeight = $currentWeights[$type]['average_weight_kg'] ?? null;
            $initialWeight = $initialWeights[$type]['average_weight_kg'] ?? null;

            $fish[] = [
                'fish_type' => $type,
                'label' => $labels[$type] ?? $type,
                'added' => (int) ($stockByType[$type]['added'] ?? 0),
                'removed' => (int) ($stockByType[$type]['removed'] ?? 0),
                'count' => $count,
                'initial_weight_g' => $this->kgToGrams($initialWeight),
                'average_weight_g' => $this->kgToGrams($currentWeight),
                'mass_kg' => $currentWeight !== null ? round($count * $currentWeight, 2) : null,
            ];
        }

        $stock = (int) $pond->current_stock;
        $totalMass = round(collect($fish)->sum(fn ($row) => $row['mass_kg'] ?? 0), 2);

        $feedKg = round((float) $pond->dailyReports()->sum('feeding_kg'), 2);
        $branKg = round((float) $pond->dailyReports()->sum('wheat_bran_kg'), 2);
        $fertilizerKg = round((float) $pond->dailyReports()->sum('fertilizer_kg'), 2);
        $feedTotalKg = round($feedKg + $branKg, 2);

        $sazanMainWeight = $this->latestMainReportWeight($pond, 'sazan');
        $sazanFeedKg = $sazanMainWeight
            ? $this->feedTotalUntil($pond, $sazanMainWeight['source_date'])
            : $feedTotalKg;

        return response()->json([
            'pond' => $pond->only(['id', 'name', 'region', 'area_m2', 'current_stock']),
            'totals' => [
                'fish_count' => $stock,
                'total_mass_kg' => $totalMass,
                'feed_kg' => $feedKg,
                'bran_kg' => $branKg,
                'feed_total_kg' => $feedTotalKg,
                'fertilizer_kg' => $fertilizerKg,
            ],
            'sazan' => $this->sazanPerformance(
                collect($fish)->firstWhere('fish_type', 'sazan'),
                $sazanFeedKg,
                $currentWeights['sazan'] ?? null,
                $initialWeights['sazan'] ?? null,
                $sazanMainWeight !== null
            ),
            'other_fish' => array_values(array_filter($fish, fn ($row) => $row['fish_type'] !== 'sazan' && $row['count'] > 0)),
            'fish_stock' => array_values(array_filter($fish, fn ($row) => $row['count'] > 0)),
            'fish_list' => array_values($fish),
            'fish_growth' => $this->fishGrowthBoard($pond, $fish),
            'feed_consumption' => $this->feedConsumptionTrend($pond),
            'water_tests' => $this->waterTestsLog($pond),
        ]);
    }

    /**
     * Suv testlari tarixi — faqat kamida bitta ko‘rsatkichi bor yozuvlar.
     *
     * @return list<array<string, mixed>>
     */
    private function waterTestsLog(Pond $pond): array
    {
        $reports = $pond->mainReports()
            ->with('user:id,name')
            ->where(function ($q) {
                $q->whereNotNull('do_mg_l')
                    ->orWhereNotNull('ph_level')
                    ->orWhereNotNull('ammonia_mg_l')
                    ->orWhereNotNull('nitrite_mg_l')
                    ->orWhereNotNull('water_transparency_cm');
            })
            ->latest('report_date')
            ->latest('id')
            ->limit(100)
            ->get();

        return $reports->map(function (MainReport $report) {
            $time = $report->test_time;
            if (is_string($time) && strlen($time) >= 5) {
                $time = substr($time, 0, 5);
            } elseif ($time) {
                $time = optional($time)->format('H:i');
            } else {
                $time = null;
            }

            return [
                'id' => $report->id,
                'report_date' => optional($report->report_date)->format('Y-m-d'),
                'test_time' => $time,
                'do_mg_l' => $report->do_mg_l !== null ? (float) $report->do_mg_l : null,
                'ph_level' => $report->ph_level !== null ? (float) $report->ph_level : null,
                'ammonia_mg_l' => $report->ammonia_mg_l !== null ? (float) $report->ammonia_mg_l : null,
                'nitrite_mg_l' => $report->nitrite_mg_l !== null ? (float) $report->nitrite_mg_l : null,
                'water_transparency_cm' => $report->water_transparency_cm !== null
                    ? (float) $report->water_transparency_cm
                    : null,
                'submitted_by' => $report->user?->name,
                'submitted_by_id' => $report->user_id,
            ];
        })->values()->all();
    }

    private function feedConsumptionTrend(Pond $pond): array
    {
        $reports = $pond->dailyReports()
            ->orderBy('report_date')
            ->get(['report_date', 'feed_name', 'feeding_kg', 'wheat_bran_kg']);

        $series = [];
        $cumulative = 0.0;
        $prevDaily = null;

        foreach ($reports as $report) {
            $feeding = round((float) $report->feeding_kg, 2);
            $bran = round((float) $report->wheat_bran_kg, 2);
            $daily = round($feeding + $bran, 2);
            $cumulative = round($cumulative + $daily, 2);

            $deltaKg = $prevDaily !== null ? round($daily - $prevDaily, 2) : null;
            $deltaPercent = ($prevDaily !== null && $prevDaily > 0)
                ? round(($deltaKg / $prevDaily) * 100, 1)
                : null;

            $series[] = [
                'date' => $report->report_date->format('Y-m-d'),
                'feed_name' => $report->feed_name,
                'feeding_kg' => $feeding,
                'wheat_bran_kg' => $bran,
                'feed_total_kg' => $daily,
                'cumulative_kg' => $cumulative,
                'delta_kg' => $deltaKg,
                'delta_percent' => $deltaPercent,
            ];

            $prevDaily = $daily;
        }

        $days = count($series);
        $totalKg = $cumulative;
        $avgDaily = $days > 0 ? round($totalKg / $days, 2) : 0.0;

        $trendDirection = 'stable';
        $trendPercent = null;
        if ($days >= 4) {
            $half = (int) floor($days / 2);
            $firstHalf = array_slice($series, 0, $half);
            $secondHalf = array_slice($series, $half);
            $firstAvg = collect($firstHalf)->avg('feed_total_kg') ?: 0;
            $secondAvg = collect($secondHalf)->avg('feed_total_kg') ?: 0;
            if ($firstAvg > 0) {
                $trendPercent = round((($secondAvg - $firstAvg) / $firstAvg) * 100, 1);
                $trendDirection = $trendPercent > 5 ? 'up' : ($trendPercent < -5 ? 'down' : 'stable');
            }
        }

        return [
            'series' => $series,
            'summary' => [
                'days_with_feed' => $days,
                'total_kg' => $totalKg,
                'average_daily_kg' => $avgDaily,
                'first_date' => $series[0]['date'] ?? null,
                'last_date' => $series[$days - 1]['date'] ?? null,
                'latest_daily_kg' => $series[$days - 1]['feed_total_kg'] ?? null,
                'trend_direction' => $trendDirection,
                'trend_percent' => $trendPercent,
            ],
        ];
    }

    /** @param list<array<string, mixed>> $fishList */
    private function fishGrowthBoard(Pond $pond, array $fishList): array
    {
        $labels = FishTypes::labels();
        $board = [];

        foreach ($fishList as $fish) {
            $type = $fish['fish_type'];
            $initial = $this->weightedAverageFromAdditions($pond, $type);
            $currentMeta = $this->latestMainReportWeight($pond, $type) ?? $initial;
            $hasWeighing = ($currentMeta['source'] ?? null) === 'main_report';
            $series = $this->weightSeriesForType($pond, $type, $initial);

            $initialG = $this->kgToGrams($initial['average_weight_kg'] ?? null);
            $currentG = $fish['average_weight_g'];
            $gainPerFishG = null;
            $growthDays = 0;
            $dailyGrowthG = null;

            if ($hasWeighing && $initialG !== null && $currentG !== null) {
                $gainPerFishG = round($currentG - $initialG, 1);
                $growthDays = $this->growthDays($initial, $currentMeta);
                $dailyGrowthG = ($growthDays > 0 && $gainPerFishG !== null)
                    ? round($gainPerFishG / $growthDays, 1)
                    : null;
            }

            $board[] = [
                'fish_type' => $type,
                'label' => $labels[$type] ?? $type,
                'count' => $fish['count'],
                'added' => $fish['added'],
                'removed' => $fish['removed'],
                'mass_kg' => $fish['mass_kg'],
                'initial_weight_g' => $initialG,
                'initial_date' => $initial['source_date'] ?? null,
                'current_weight_g' => $currentG,
                'current_date' => $currentMeta['source_date'] ?? null,
                'gain_per_fish_g' => $gainPerFishG,
                'growth_days' => $growthDays,
                'daily_growth_per_fish_g' => $dailyGrowthG,
                'has_weighing' => $hasWeighing,
                'series' => $series,
            ];
        }

        return $board;
    }

    private function weightSeriesForType(Pond $pond, string $type, ?array $initial): array
    {
        $series = [];

        if ($initial) {
            $series[] = [
                'date' => $initial['source_date'],
                'weight_g' => $this->kgToGrams($initial['average_weight_kg']),
                'source' => 'stock_add',
            ];
        }

        $reports = $pond->mainReports()
            ->whereHas('fishWeights', fn ($q) => $q->where('fish_type', $type))
            ->with(['fishWeights' => fn ($q) => $q->where('fish_type', $type)])
            ->orderBy('report_date')
            ->get(['id', 'report_date']);

        foreach ($reports as $report) {
            $weight = $report->fishWeights->first();
            if (! $weight) {
                continue;
            }

            $series[] = [
                'date' => optional($report->report_date)->format('Y-m-d'),
                'weight_g' => $this->kgToGrams((float) $weight->average_weight_kg),
                'source' => 'main_report',
            ];
        }

        return collect($series)
            ->filter(fn (array $row) => $row['date'] && $row['weight_g'] !== null)
            ->unique('date')
            ->sortBy('date')
            ->values()
            ->all();
    }

    public function fishStock(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $labels = FishTypes::labels();
        $currentWeights = $this->suggestedFishWeights($pond);
        $stockByType = $this->stockByFishType($pond);

        $items = [];
        foreach (FishTypes::all() as $type) {
            $count = max(0, (int) ($stockByType[$type]['count'] ?? 0));
            if ($count === 0) {
                continue;
            }

            $weightMeta = $currentWeights[$type] ?? null;
            $weightKg = $weightMeta['average_weight_kg'] ?? null;
            $items[] = [
                'fish_type' => $type,
                'label' => $labels[$type] ?? $type,
                'count' => $count,
                'average_weight_g' => $this->kgToGrams($weightKg),
                'weight_source' => $weightMeta['source'] ?? null,
                'weight_source_date' => $weightMeta['source_date'] ?? null,
                'mass_kg' => $weightKg !== null ? round($count * $weightKg, 2) : null,
            ];
        }

        return response()->json([
            'pond' => $pond->only(['id', 'name', 'current_stock']),
            'stock' => $items,
            'fish_types' => $labels,
        ]);
    }

    private function stockByFishType(Pond $pond): array
    {
        $addedByType = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'add')
            ->whereNotNull('fish_type')
            ->select('fish_type', DB::raw('SUM(quantity) as qty'))
            ->groupBy('fish_type')
            ->pluck('qty', 'fish_type');

        $removedByType = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->whereIn('type', ['sale', 'loss'])
            ->whereNotNull('fish_type')
            ->select('fish_type', DB::raw('SUM(quantity) as qty'))
            ->groupBy('fish_type')
            ->pluck('qty', 'fish_type');

        $untypedRemoved = (int) FishOperation::query()
            ->where('pond_id', $pond->id)
            ->whereIn('type', ['sale', 'loss'])
            ->whereNull('fish_type')
            ->sum('quantity');

        $result = [];
        $remainingAfterTyped = [];

        foreach (FishTypes::all() as $type) {
            $added = (int) ($addedByType[$type] ?? 0);
            $removed = (int) ($removedByType[$type] ?? 0);
            $count = max(0, $added - $removed);
            $remainingAfterTyped[$type] = $count;
            $result[$type] = [
                'added' => $added,
                'removed' => $removed,
                'count' => $count,
            ];
        }

        $pool = array_sum($remainingAfterTyped);
        if ($untypedRemoved > 0 && $pool > 0) {
            $left = $untypedRemoved;
            foreach (FishTypes::all() as $type) {
                if ($left <= 0) {
                    break;
                }
                $share = (int) round(($remainingAfterTyped[$type] / $pool) * $untypedRemoved);
                $share = min($share, $remainingAfterTyped[$type], $left);
                $result[$type]['count'] = max(0, $result[$type]['count'] - $share);
                $result[$type]['removed'] += $share;
                $left -= $share;
            }
        }

        return $result;
    }

    private function sazanPerformance(
        ?array $sazan,
        float $feedTotalKg,
        ?array $currentWeight,
        ?array $initialWeight,
        bool $hasMainReport
    ): ?array {
        if (! $sazan || ($sazan['count'] ?? 0) <= 0) {
            return $sazan ? [
                'count' => 0,
                'initial_weight_g' => $this->kgToGrams($initialWeight['average_weight_kg'] ?? null),
                'average_weight_g' => $this->kgToGrams($currentWeight['average_weight_kg'] ?? null),
                'mass_kg' => 0,
                'gain_per_fish_g' => null,
                'biomass_gain_kg' => null,
                'growth_days' => 0,
                'daily_growth_per_fish_g' => null,
                'feed_total_kg' => $feedTotalKg,
                'fcr' => null,
            ] : null;
        }

        $current = $currentWeight['average_weight_kg'] ?? null;
        $initial = $initialWeight['average_weight_kg'] ?? null;

        $gainPerFishKg = null;
        $biomassGainKg = null;
        $days = 0;
        $dailyGrowthGram = null;
        $fcr = null;

        if ($hasMainReport && $current !== null && $initial !== null) {
            $gainPerFishKg = round($current - $initial, 3);
            $biomassGainKg = round($gainPerFishKg * $sazan['count'], 2);
            $days = $this->growthDays($initialWeight, $currentWeight);
            $dailyGrowthGram = ($days > 0)
                ? round(($gainPerFishKg * 1000) / $days, 1)
                : null;
            $fcr = ($biomassGainKg !== null && $biomassGainKg > 0)
                ? round($feedTotalKg / $biomassGainKg, 2)
                : null;
        }

        return [
            'count' => $sazan['count'],
            'initial_weight_g' => $this->kgToGrams($initial),
            'average_weight_g' => $this->kgToGrams($current),
            'mass_kg' => $sazan['mass_kg'],
            'gain_per_fish_g' => $this->kgToGrams($gainPerFishKg),
            'biomass_gain_kg' => $biomassGainKg,
            'growth_days' => $days,
            'daily_growth_per_fish_g' => $dailyGrowthGram,
            'feed_total_kg' => $feedTotalKg,
            'fcr' => $fcr,
        ];
    }

    private function kgToGrams(?float $weight): ?float
    {
        return $weight === null ? null : round($weight * 1000, 1);
    }

    private function growthDays(?array $initialWeight, ?array $currentWeight): int
    {
        if (empty($initialWeight['source_date']) || empty($currentWeight['source_date'])) {
            return 0;
        }

        $from = Carbon::parse($initialWeight['source_date']);
        $to = Carbon::parse($currentWeight['source_date']);

        return $to->greaterThan($from) ? $from->diffInDays($to) : 0;
    }

    private function initialFishWeights(Pond $pond): array
    {
        $weights = [];

        foreach (FishTypes::all() as $type) {
            $weighted = $this->weightedAverageFromAdditions($pond, $type);
            if ($weighted) {
                $weights[$type] = $weighted;
            }
        }

        return $weights;
    }

    private function weightedAverageFromAdditions(Pond $pond, string $type): ?array
    {
        $operations = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'add')
            ->where('fish_type', $type)
            ->whereNotNull('average_weight_kg')
            ->where('quantity', '>', 0)
            ->orderBy('operated_at')
            ->get(['quantity', 'average_weight_kg', 'operated_at']);

        if ($operations->isEmpty()) {
            return null;
        }

        $totalQty = (int) $operations->sum('quantity');
        if ($totalQty <= 0) {
            return null;
        }

        $totalMassGrams = $operations->sum(
            fn ($operation) => $operation->quantity * $this->kgToGrams((float) $operation->average_weight_kg)
        );

        $averageGrams = round($totalMassGrams / $totalQty, 1);

        return [
            'average_weight_kg' => round($averageGrams / 1000, 4),
            'source' => 'stock_add',
            'source_date' => optional($operations->first()->operated_at)->format('Y-m-d'),
        ];
    }

    private function latestMainReportWeight(Pond $pond, string $type): ?array
    {
        $fromMain = $pond->mainReports()
            ->whereHas('fishWeights', fn ($q) => $q->where('fish_type', $type))
            ->with(['fishWeights' => fn ($q) => $q->where('fish_type', $type)])
            ->latest('report_date')
            ->first();

        if (! $fromMain || $fromMain->fishWeights->isEmpty()) {
            return null;
        }

        return [
            'average_weight_kg' => (float) $fromMain->fishWeights->first()->average_weight_kg,
            'source' => 'main_report',
            'source_date' => optional($fromMain->report_date)->format('Y-m-d'),
        ];
    }

    private function feedTotalUntil(Pond $pond, string $date): float
    {
        $feedKg = (float) $pond->dailyReports()->whereDate('report_date', '<=', $date)->sum('feeding_kg');
        $branKg = (float) $pond->dailyReports()->whereDate('report_date', '<=', $date)->sum('wheat_bran_kg');

        return round($feedKg + $branKg, 2);
    }

    public function mainReports(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $reports = $pond->mainReports()
            ->with(['user:id,name', 'fishWeights'])
            ->latest('report_date')
            ->paginate(20);

        return response()->json([
            'pond' => $pond,
            'stock' => $pond->current_stock,
            'suggested_weights' => collect($this->suggestedFishWeights($pond))
                ->map(fn ($weight) => [
                    ...$weight,
                    'average_weight_g' => $this->kgToGrams($weight['average_weight_kg']),
                ]),
            'fish_types' => FishTypes::labels(),
            'water_quality_standards' => WaterQualityStandards::clientConfig(),
            'reports' => $reports,
        ]);
    }

    protected function directorFarmIds(Request $request)
    {
        return Farm::query()
            ->when($request->user()->isOwner(), function ($query) use ($request) {
                $directorId = OwnerAccess::actingDirectorId($request);
                if ($directorId) {
                    $query->where('director_id', $directorId);
                } else {
                    $query->whereRaw('0 = 1');
                }
            }, function ($query) use ($request) {
                $query->where('director_id', $request->user()->id);
            })
            ->pluck('id');
    }

    public function workReminders(Request $request): JsonResponse
    {
        $farmIds = $this->directorFarmIds($request);
        $alerts = WorkScheduleReminders::forFarms($farmIds);

        return response()->json([
            'alerts' => $alerts,
            'rules' => [
                'timezone' => WorkScheduleReminders::TZ,
                'feed_hour' => WorkScheduleReminders::FEED_REMIND_HOUR,
                'water_max_gap_days' => WorkScheduleReminders::WATER_MAX_GAP_DAYS,
                'weigh_max_gap_days' => WorkScheduleReminders::WEIGH_MAX_GAP_DAYS,
            ],
        ]);
    }

    public function waterQualityAlerts(Request $request): JsonResponse
    {
        $farmIds = $this->directorFarmIds($request);

        $since = Carbon::now()->subDays(7)->startOfDay();

        $reports = MainReport::query()
            ->with(['pond:id,name,farm_id,closed_at,current_stock', 'pond.farm:id,name'])
            ->whereHas('pond', function ($q) use ($farmIds) {
                $q->whereIn('farm_id', $farmIds)
                    ->whereNull('closed_at')
                    ->where('current_stock', '>', 0);
            })
            ->where('report_date', '>=', $since)
            ->latest('report_date')
            ->latest('id')
            ->limit(40)
            ->get();

        $alerts = [];
        foreach ($reports as $report) {
            $assessment = WaterQualityStandards::evaluateReport($report);
            if ($assessment['level'] === WaterQualityStandards::LEVEL_OK) {
                continue;
            }
            if (empty($assessment['issues'])) {
                continue;
            }

            $pond = $report->pond;
            $alerts[] = [
                'type' => 'water-quality',
                'tone' => $assessment['level'] === WaterQualityStandards::LEVEL_DANGER ? 'danger' : 'warning',
                'level' => $assessment['level'],
                'farmId' => $pond?->farm_id,
                'pondId' => $pond?->id,
                'itemId' => $report->id,
                'reportId' => $report->id,
                'reportDate' => optional($report->report_date)->format('Y-m-d'),
                'testTime' => is_string($report->test_time)
                    ? substr($report->test_time, 0, 5)
                    : (optional($report->test_time)->format('H:i')),
                'band' => $assessment['band'],
                'issues' => $assessment['issues'],
                'farmName' => $pond?->farm?->name,
                'pondName' => $pond?->name,
            ];
        }

        return response()->json([
            'alerts' => array_slice($alerts, 0, 15),
            'standards' => WaterQualityStandards::clientConfig(),
        ]);
    }

    public function waterQualityDetail(Request $request, Pond $pond, MainReport $mainReport): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensureBelongsToPond($pond, $mainReport->pond_id);

        $assessment = WaterQualityStandards::evaluateReport($mainReport);
        $time = $mainReport->test_time;
        if (is_string($time) && strlen($time) >= 5) {
            $time = substr($time, 0, 5);
        } elseif ($time) {
            $time = optional($time)->format('H:i');
        }

        return response()->json([
            'pond' => [
                'id' => $pond->id,
                'name' => $pond->name,
                'current_stock' => $pond->current_stock,
            ],
            'report' => [
                'id' => $mainReport->id,
                'report_date' => optional($mainReport->report_date)->format('Y-m-d'),
                'test_time' => $time,
                'do_mg_l' => $mainReport->do_mg_l,
                'ph_level' => $mainReport->ph_level,
                'ammonia_mg_l' => $mainReport->ammonia_mg_l,
                'nitrite_mg_l' => $mainReport->nitrite_mg_l,
                'water_transparency_cm' => $mainReport->water_transparency_cm,
            ],
            'assessment' => $assessment,
            'standards' => WaterQualityStandards::clientConfig(),
            'specialist_phone' => '+998774442525',
        ]);
    }

    public function storeMainReport(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);

        $data = $request->validate([
            'report_date' => 'required|date',
            'test_time' => 'nullable|date_format:H:i',
            'do_mg_l' => 'nullable|numeric|min:0',
            'ph_level' => 'nullable|numeric|min:0|max:14',
            'ammonia_mg_l' => 'nullable|numeric|min:0',
            'nitrite_mg_l' => 'nullable|numeric|min:0',
            'water_transparency_cm' => 'nullable|numeric|min:0',
            'weigh_fish' => 'sometimes|boolean',
            'fish_weights' => 'nullable|array',
            'fish_weights.*.fish_type' => ['required_with:fish_weights', Rule::in(FishTypes::all())],
            'fish_weights.*.average_weight_g' => 'required_with:fish_weights|numeric|min:1',
        ]);

        $weighFish = (bool) ($data['weigh_fish'] ?? false);
        $weights = collect($data['fish_weights'] ?? [])
            ->filter(fn ($row) => ! empty($row['fish_type']) && isset($row['average_weight_g']) && $row['average_weight_g'] !== '')
            ->unique('fish_type')
            ->values();

        if ($weighFish && $weights->isEmpty()) {
            throw ValidationException::withMessages([
                'fish_weights' => ['Baliqlarni tortish uchun kamida bitta tur vaznini kiriting.'],
            ]);
        }

        $report = DB::transaction(function () use ($request, $pond, $data, $weighFish, $weights) {
            $report = MainReport::updateOrCreate(
                [
                    'pond_id' => $pond->id,
                    'report_date' => $data['report_date'],
                ],
                [
                    'user_id' => $request->user()->id,
                    'test_time' => $data['test_time'] ?? null,
                    'do_mg_l' => $data['do_mg_l'] ?? null,
                    'ph_level' => $data['ph_level'] ?? null,
                    'ammonia_mg_l' => $data['ammonia_mg_l'] ?? null,
                    'nitrite_mg_l' => $data['nitrite_mg_l'] ?? null,
                    'water_transparency_cm' => $data['water_transparency_cm'] ?? null,
                ]
            );

            if ($weighFish) {
                $report->fishWeights()->delete();
                foreach ($weights as $row) {
                    $report->fishWeights()->create([
                        'fish_type' => $row['fish_type'],
                        'average_weight_kg' => $row['average_weight_g'] / 1000,
                    ]);
                }
            }

            return $report->load(['user:id,name', 'fishWeights']);
        });

        $assessment = WaterQualityStandards::evaluateReport($report);

        $this->logActivity(
            $pond,
            $request->user()->id,
            'main_report',
            'Asosiy hisobot topshirildi',
            $data['report_date'].' sanasi uchun asosiy hisobot saqlandi',
            [
                'test_time' => $data['test_time'] ?? null,
                'do_mg_l' => $data['do_mg_l'] ?? null,
                'ph_level' => $data['ph_level'] ?? null,
                'ammonia_mg_l' => $data['ammonia_mg_l'] ?? null,
                'nitrite_mg_l' => $data['nitrite_mg_l'] ?? null,
                'water_transparency_cm' => $data['water_transparency_cm'] ?? null,
                'water_quality' => $assessment,
                'fish_weights' => $weighFish
                    ? $weights->map(fn ($row) => [
                        'fish_type' => $row['fish_type'],
                        'average_weight_g' => $row['average_weight_g'],
                    ])->all()
                    : null,
            ],
            $data['report_date']
        );

        return response()->json([
            ...$report->toArray(),
            'water_quality' => $assessment,
        ], 201);
    }

    public function updateMainReport(Request $request, Pond $pond, MainReport $mainReport): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);
        $this->ensureBelongsToPond($pond, $mainReport->pond_id);

        $data = $request->validate([
            'report_date' => [
                'required',
                'date',
                Rule::unique('main_reports', 'report_date')
                    ->where(fn ($q) => $q->where('pond_id', $pond->id))
                    ->ignore($mainReport->id),
            ],
            'test_time' => 'nullable|date_format:H:i',
            'do_mg_l' => 'nullable|numeric|min:0',
            'ph_level' => 'nullable|numeric|min:0|max:14',
            'ammonia_mg_l' => 'nullable|numeric|min:0',
            'nitrite_mg_l' => 'nullable|numeric|min:0',
            'water_transparency_cm' => 'nullable|numeric|min:0',
            'weigh_fish' => 'sometimes|boolean',
            'fish_weights' => 'nullable|array',
            'fish_weights.*.fish_type' => ['required_with:fish_weights', Rule::in(FishTypes::all())],
            'fish_weights.*.average_weight_g' => 'required_with:fish_weights|numeric|min:1',
        ]);

        $weighFish = (bool) ($data['weigh_fish'] ?? false);
        $weights = collect($data['fish_weights'] ?? [])
            ->filter(fn ($row) => ! empty($row['fish_type']) && isset($row['average_weight_g']) && $row['average_weight_g'] !== '')
            ->unique('fish_type')
            ->values();

        if ($weighFish && $weights->isEmpty()) {
            throw ValidationException::withMessages([
                'fish_weights' => ['Baliqlarni tortish uchun kamida bitta tur vaznini kiriting.'],
            ]);
        }

        $report = DB::transaction(function () use ($request, $mainReport, $data, $weighFish, $weights) {
            $mainReport->update([
                'user_id' => $request->user()->id,
                'report_date' => $data['report_date'],
                'test_time' => $data['test_time'] ?? null,
                'do_mg_l' => $data['do_mg_l'] ?? null,
                'ph_level' => $data['ph_level'] ?? null,
                'ammonia_mg_l' => $data['ammonia_mg_l'] ?? null,
                'nitrite_mg_l' => $data['nitrite_mg_l'] ?? null,
                'water_transparency_cm' => $data['water_transparency_cm'] ?? null,
            ]);

            if ($weighFish || array_key_exists('fish_weights', $data)) {
                $mainReport->fishWeights()->delete();
                foreach ($weights as $row) {
                    $mainReport->fishWeights()->create([
                        'fish_type' => $row['fish_type'],
                        'average_weight_kg' => $row['average_weight_g'] / 1000,
                    ]);
                }
            }

            return $mainReport->load(['user:id,name', 'fishWeights']);
        });

        $assessment = WaterQualityStandards::evaluateReport($report);

        $this->logActivity(
            $pond,
            $request->user()->id,
            'main_report_updated',
            'Asosiy hisobot o‘zgartirildi',
            $data['report_date'].' sanasi uchun asosiy hisobot yangilandi',
            [
                'test_time' => $data['test_time'] ?? null,
                'do_mg_l' => $data['do_mg_l'] ?? null,
                'ph_level' => $data['ph_level'] ?? null,
                'ammonia_mg_l' => $data['ammonia_mg_l'] ?? null,
                'nitrite_mg_l' => $data['nitrite_mg_l'] ?? null,
                'water_transparency_cm' => $data['water_transparency_cm'] ?? null,
                'water_quality' => $assessment,
                'fish_weights' => $weighFish
                    ? $weights->map(fn ($row) => [
                        'fish_type' => $row['fish_type'],
                        'average_weight_g' => $row['average_weight_g'],
                    ])->all()
                    : null,
            ],
            $data['report_date']
        );

        return response()->json([
            ...$report->toArray(),
            'water_quality' => $assessment,
        ]);
    }

    public function destroyMainReport(Request $request, Pond $pond, MainReport $mainReport): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);
        $this->ensureBelongsToPond($pond, $mainReport->pond_id);

        $date = optional($mainReport->report_date)->format('Y-m-d');
        $mainReport->delete();

        $this->logActivity(
            $pond,
            $request->user()->id,
            'main_report_deleted',
            'Asosiy hisobot o‘chirildi',
            $date.' sanasidagi asosiy hisobot o‘chirildi',
            ['report_date' => $date],
            $date
        );

        return response()->json(['message' => 'Asosiy hisobot o\'chirildi']);
    }

    public function fishOperations(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $operations = $pond->fishOperations()
            ->with('user:id,name')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->latest('operated_at')
            ->paginate(50);

        return response()->json($operations);
    }

    public function storeFishOperation(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);

        $data = $request->validate([
            'type' => 'required|in:add,sale,loss',
            'fish_type' => ['required', Rule::in(FishTypes::all())],
            'quantity' => 'nullable|integer|min:1',
            'total_weight_kg' => 'nullable|numeric|min:0.001',
            'average_weight_g' => 'nullable|numeric|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'operated_at' => 'required|date',
        ]);

        [$quantity, $totalWeightKg, $averageWeightKg] = $this->resolveFishQuantity($data);
        $moneyAmount = $this->resolveFishMoneyAmount($data, $totalWeightKg);

        $operation = DB::transaction(function () use ($request, $pond, $data, $quantity, $totalWeightKg, $averageWeightKg, $moneyAmount) {
            $pond = Pond::whereKey($pond->id)->lockForUpdate()->first();

            if ($data['type'] === 'add') {
                $pond->current_stock += $quantity;
            } else {
                $pond->current_stock -= $quantity;
            }
            $pond->save();

            return FishOperation::create([
                'pond_id' => $pond->id,
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'fish_type' => $data['fish_type'],
                'quantity' => $quantity,
                'average_weight_kg' => $averageWeightKg,
                'total_weight_kg' => $totalWeightKg,
                'amount' => $moneyAmount,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'operated_at' => $data['operated_at'],
            ]);
        });

        if ($operation->type === 'add') {
            $this->fishPurchaseExpense->sync(
                $operation,
                $pond,
                $request->user(),
                isset($data['unit_price']) ? (float) $data['unit_price'] : null
            );
            $operation = $operation->fresh();
        }

        $typeTitles = [
            'add' => 'Baliq qo‘shildi',
            'sale' => 'Baliq sotildi',
            'loss' => 'Baliq poteryasi qayd etildi',
        ];

        $description = $operation->type === 'sale'
            ? $operation->total_weight_kg.' kg'
            : $operation->quantity.' dona';
        if ($operation->fish_type) {
            $description .= ' '.$operation->fish_type;
        }
        if ($operation->type !== 'sale' && $operation->total_weight_kg) {
            $description .= ', '.$operation->total_weight_kg.' kg';
        }
        if ($operation->type !== 'sale' && $operation->average_weight_kg) {
            $description .= ', o‘rtacha vazni '.$this->kgToGrams((float) $operation->average_weight_kg).' g';
        }

        $this->logActivity(
            $pond,
            $request->user()->id,
            'fish_'.$operation->type,
            $typeTitles[$operation->type],
            $description,
            [
                'fish_type' => $operation->fish_type,
                'quantity' => $operation->type === 'sale' ? null : $operation->quantity,
                'total_weight_kg' => $operation->total_weight_kg,
                'average_weight_g' => $operation->type !== 'sale' && $operation->average_weight_kg !== null
                    ? $this->kgToGrams((float) $operation->average_weight_kg)
                    : null,
                'unit_price' => isset($data['unit_price']) ? (float) $data['unit_price'] : null,
                'amount' => $operation->amount,
                'notes' => $operation->notes,
            ],
            $operation->operated_at
        );

        return response()->json([
            'operation' => $operation,
            'current_stock' => $pond->fresh()->current_stock,
        ], 201);
    }

    public function updateFishOperation(Request $request, Pond $pond, FishOperation $fishOperation): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);
        $this->ensureBelongsToPond($pond, $fishOperation->pond_id);

        $data = $request->validate([
            'type' => 'required|in:add,sale,loss',
            'fish_type' => ['required', Rule::in(FishTypes::all())],
            'quantity' => 'nullable|integer|min:1',
            'total_weight_kg' => 'nullable|numeric|min:0.001',
            'average_weight_g' => 'nullable|numeric|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'operated_at' => 'required|date',
        ]);

        [$quantity, $totalWeightKg, $averageWeightKg] = $this->resolveFishQuantity($data);
        $moneyAmount = $this->resolveFishMoneyAmount($data, $totalWeightKg);

        $operation = DB::transaction(function () use ($request, $pond, $fishOperation, $data, $quantity, $totalWeightKg, $averageWeightKg, $moneyAmount) {
            $pond = Pond::whereKey($pond->id)->lockForUpdate()->first();

            if ($fishOperation->type === 'add') {
                $pond->current_stock -= $fishOperation->quantity;
            } else {
                $pond->current_stock += $fishOperation->quantity;
            }

            if ($data['type'] === 'add') {
                $pond->current_stock += $quantity;
            } else {
                $pond->current_stock -= $quantity;
            }

            $pond->save();

            $fishOperation->update([
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'fish_type' => $data['fish_type'],
                'quantity' => $quantity,
                'average_weight_kg' => $averageWeightKg,
                'total_weight_kg' => $totalWeightKg,
                'amount' => $moneyAmount,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'operated_at' => $data['operated_at'],
            ]);

            return $fishOperation->fresh();
        });

        $this->fishPurchaseExpense->sync(
            $operation,
            $pond,
            $request->user(),
            $operation->type === 'add' && isset($data['unit_price'])
                ? (float) $data['unit_price']
                : null
        );
        $operation = $operation->fresh();

        $this->logActivity(
            $pond,
            $request->user()->id,
            'fish_'.$operation->type.'_updated',
            'Baliq operatsiyasi o‘zgartirildi',
            $operation->type === 'sale'
                ? $operation->total_weight_kg.' kg sotuv yangilandi'
                : $operation->quantity.' dona yangilandi',
            [
                'fish_type' => $operation->fish_type,
                'quantity' => $operation->type === 'sale' ? null : $operation->quantity,
                'total_weight_kg' => $operation->total_weight_kg,
                'average_weight_g' => $operation->type !== 'sale' && $operation->average_weight_kg !== null
                    ? $this->kgToGrams((float) $operation->average_weight_kg)
                    : null,
                'unit_price' => isset($data['unit_price']) ? (float) $data['unit_price'] : null,
                'amount' => $operation->amount,
                'notes' => $operation->notes,
            ],
            $operation->operated_at
        );

        return response()->json([
            'operation' => $operation,
            'current_stock' => $pond->fresh()->current_stock,
        ]);
    }

    private function resolveFishQuantity(array $data): array
    {
        $averageWeightG = isset($data['average_weight_g']) && $data['average_weight_g'] !== '' && $data['average_weight_g'] !== null
            ? (float) $data['average_weight_g']
            : null;
        $totalWeightKg = isset($data['total_weight_kg']) && $data['total_weight_kg'] !== '' && $data['total_weight_kg'] !== null
            ? (float) $data['total_weight_kg']
            : null;
        $quantity = isset($data['quantity']) && $data['quantity'] !== '' && $data['quantity'] !== null
            ? (int) $data['quantity']
            : null;

        // add / sale / loss: son + o‘rtacha yoki son + umumiy kg (3-chisi avto)
        $provided = collect([
            $quantity !== null,
            $totalWeightKg !== null,
            $averageWeightG !== null,
        ])->filter()->count();

        if ($data['type'] === 'add') {
            if ($quantity === null) {
                throw ValidationException::withMessages([
                    'quantity' => ['Baliq sonini kiriting.'],
                ]);
            }
            if ($averageWeightG === null && $totalWeightKg === null) {
                throw ValidationException::withMessages([
                    'average_weight_g' => ['O‘rtacha vazn (g) yoki umumiy massa (kg) kiriting.'],
                ]);
            }
        } elseif ($provided < 2) {
            throw ValidationException::withMessages([
                'quantity' => ['Baliq soni, o‘rtacha vazn (g) yoki umumiy kg dan kamida 2 tasini kiriting.'],
            ]);
        }

        if ($quantity !== null && $averageWeightG !== null && $totalWeightKg === null) {
            $totalWeightKg = round($quantity * ($averageWeightG / 1000), 3);
        } elseif ($quantity !== null && $totalWeightKg !== null && $averageWeightG === null) {
            $averageWeightG = round(($totalWeightKg / $quantity) * 1000, 1);
        } elseif ($averageWeightG !== null && $totalWeightKg !== null && $quantity === null) {
            $quantity = (int) max(1, round($totalWeightKg / ($averageWeightG / 1000)));
        }

        return [$quantity, $totalWeightKg, $averageWeightG / 1000];
    }

    private function resolveFishMoneyAmount(array $data, float $totalWeightKg): ?float
    {
        if (! in_array($data['type'], ['sale', 'add'], true)) {
            return null;
        }

        if (isset($data['unit_price']) && $data['unit_price'] !== '' && $data['unit_price'] !== null) {
            $unitPrice = (float) $data['unit_price'];
            if ($unitPrice > 0 && $totalWeightKg > 0) {
                return round($totalWeightKg * $unitPrice, 2);
            }
        }

        if ($data['type'] === 'sale' && isset($data['amount']) && $data['amount'] !== '' && $data['amount'] !== null) {
            return round((float) $data['amount'], 2);
        }

        return null;
    }

    private function availableCountForType(Pond $pond, string $fishType, ?int $ignoreOperationId = null): int
    {
        $stock = $this->stockByFishType($pond);
        $count = (int) ($stock[$fishType]['count'] ?? 0);

        if ($ignoreOperationId) {
            $ignored = FishOperation::query()
                ->whereKey($ignoreOperationId)
                ->where('pond_id', $pond->id)
                ->first();

            // stockByFishType allaqachon DB dagi joriy holatni oladi;
            // update paytida eski sale/loss hali bor, shu sabab uni qaytarib qo‘shamiz.
            if ($ignored && in_array($ignored->type, ['sale', 'loss'], true) && $ignored->fish_type === $fishType) {
                $count += (int) $ignored->quantity;
            }
            if ($ignored && $ignored->type === 'add' && $ignored->fish_type === $fishType) {
                $count -= (int) $ignored->quantity;
            }
        }

        return max(0, $count);
    }

    public function destroyFishOperation(Request $request, Pond $pond, FishOperation $fishOperation): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);
        $this->ensureBelongsToPond($pond, $fishOperation->pond_id);

        DB::transaction(function () use ($pond, $fishOperation) {
            $pond = Pond::whereKey($pond->id)->lockForUpdate()->first();

            if ($fishOperation->type === 'add') {
                if ($pond->current_stock < $fishOperation->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Bu qo‘shishni o‘chirib bo‘lmaydi: zaxira yetarli emas.'],
                    ]);
                }
                $pond->current_stock -= $fishOperation->quantity;
            } else {
                $pond->current_stock += $fishOperation->quantity;
            }

            $pond->save();
            $this->fishPurchaseExpense->deleteFor($fishOperation);
            $fishOperation->delete();
        });

        $this->logActivity(
            $pond,
            $request->user()->id,
            'fish_operation_deleted',
            'Baliq operatsiyasi o‘chirildi',
            'Operatsiya o‘chirildi va zaxira qayta hisoblandi'
        );

        return response()->json([
            'message' => 'Operatsiya o\'chirildi',
            'current_stock' => $pond->fresh()->current_stock,
        ]);
    }

    protected function authorizeFarm(Request $request, Farm $farm): void
    {
        FarmAccess::authorizeDirectorOrOwner($request->user(), $farm);
    }

    protected function logFarmWarehouseActivity(
        Farm $farm,
        int $userId,
        string $action,
        string $title,
        ?string $description = null,
        array $details = [],
    ): void {
        $pond = $farm->ponds()->orderBy('id')->first();
        if (! $pond) {
            return;
        }

        $this->logActivity($pond, $userId, $action, $title, $description, $details);
    }

    public function closurePreview(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $pond->load(['closure.user:id,name', 'pendingClosureRequest.requester:id,name']);

        if ($pond->isClosed()) {
            return response()->json([
                'pond' => $pond->only(['id', 'name', 'region', 'current_stock', 'closed_at', 'status']),
                'closed' => true,
                'closure' => $pond->closure,
            ]);
        }

        $estimated = $this->buildEstimatedStockSnapshot($pond);
        $analysis = $this->pondClosure->buildAnalysis($pond);
        $summaryResponse = $this->summary($request, $pond);
        $summary = json_decode($summaryResponse->getContent(), true);

        $pending = $pond->pendingClosureRequest;
        if ($pending) {
            $pending->load('requester:id,name');
        }

        return response()->json([
            'pond' => $pond->only(['id', 'name', 'region', 'current_stock', 'closed_at', 'status']),
            'closed' => false,
            'estimated_stock' => $estimated,
            'analysis' => $analysis,
            'summary' => $summary,
            'pending_request' => $pending ? $this->formatClosureRequest($pending) : null,
        ]);
    }

    public function submitClosureRequest(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);

        if (! $request->user()->isDirector()) {
            abort(403, 'Faqat direktor havza yopish arizasi yuborishi mumkin');
        }

        if ($pond->pendingClosureRequest()->exists()) {
            throw ValidationException::withMessages([
                'pond' => ['Bu havza uchun kutilayotgan ariza mavjud. BOSS javobini kuting.'],
            ]);
        }

        $data = $request->validate([
            'closed_on' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'confirm_all_finished' => 'accepted',
            'confirm_no_fish_left' => 'accepted',
            'confirm_sales_recorded' => 'accepted',
        ], [
            'confirm_all_finished.accepted' => 'Koldagi barcha ishlar yakunlanganini tasdiqlang.',
            'confirm_no_fish_left.accepted' => 'Baliq qolmaganini tasdiqlang.',
            'confirm_sales_recorded.accepted' => 'Sotilgan baliqlar baliq sotish bo‘limiga kiritilganini tasdiqlang.',
        ]);

        $analysis = $this->pondClosure->buildAnalysis($pond);

        $closureRequest = PondClosureRequest::create([
            'pond_id' => $pond->id,
            'requested_by' => $request->user()->id,
            'status' => PondClosureRequest::STATUS_PENDING,
            'closed_on' => $data['closed_on'],
            'confirmations' => [
                'all_finished' => true,
                'no_fish_left' => true,
                'sales_recorded' => true,
            ],
            'notes' => $data['notes'] ?? null,
            'analysis_snapshot' => $analysis,
        ]);

        $this->logActivity(
            $pond,
            $request->user()->id,
            'pond_closure_requested',
            'Havza yopish arizasi',
            $request->user()->name.' '.$pond->name.' havzasini yopmoqchi',
            [
                'request_id' => $closureRequest->id,
                'closed_on' => $closureRequest->closed_on?->toDateString(),
            ],
            $data['closed_on']
        );

        $closureRequest->load('requester:id,name');

        return response()->json([
            'request' => $this->formatClosureRequest($closureRequest),
            'message' => 'Ariza BOSS kabinetiga yuborildi',
        ], 201);
    }

    public function closureArchive(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        if (! $pond->isClosed()) {
            abort(404, 'Havza hali yopilmagan');
        }

        $pond->load('closure.user:id,name');

        return response()->json([
            'pond' => $pond->only(['id', 'name', 'region', 'area_m2', 'farm_id', 'closed_at', 'status']),
            'closure' => $pond->closure,
            'archive' => $pond->closure?->summary_snapshot,
        ]);
    }

    public function closePond(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);
        $this->ensurePondOpen($pond);

        if (! $request->user()->isOwner()) {
            throw ValidationException::withMessages([
                'pond' => ['Havzani yopish uchun BOSS tasdiqlashi kerak. Ariza yuboring.'],
            ]);
        }

        $data = $request->validate([
            'mode' => ['required', Rule::in([PondClosure::MODE_EARLY, PondClosure::MODE_FINAL])],
            'closed_on' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'actual_lines' => 'nullable|array',
            'actual_lines.*.fish_type' => ['required_with:actual_lines', Rule::in(FishTypes::all())],
            'actual_lines.*.quantity' => 'required_with:actual_lines|integer|min:0',
            'actual_lines.*.total_weight_kg' => 'nullable|numeric|min:0',
        ]);

        if ($data['mode'] === PondClosure::MODE_FINAL && empty($data['actual_lines'])) {
            throw ValidationException::withMessages([
                'actual_lines' => ['Kol tugatishda aniq baliq soni va vaznini kiriting.'],
            ]);
        }

        $estimated = $this->buildEstimatedStockSnapshot($pond);
        $summaryResponse = $this->summary($request, $pond);
        $summarySnapshot = json_decode($summaryResponse->getContent(), true);

        $actualLines = collect($data['actual_lines'] ?? [])
            ->keyBy('fish_type')
            ->map(fn ($row) => [
                'quantity' => (int) ($row['quantity'] ?? 0),
                'total_weight_kg' => isset($row['total_weight_kg']) ? round((float) $row['total_weight_kg'], 3) : null,
            ]);

        $adjustments = [
            'losses' => [],
            'surplus' => [],
        ];

        $closure = DB::transaction(function () use ($request, $pond, $data, $estimated, $summarySnapshot, $actualLines, &$adjustments) {
            $pond = Pond::whereKey($pond->id)->lockForUpdate()->first();
            $weights = $this->suggestedFishWeights($pond);
            $closedOn = Carbon::parse($data['closed_on'])->toDateString();

            if ($data['mode'] === PondClosure::MODE_EARLY) {
                foreach ($estimated as $line) {
                    $qty = (int) ($line['count'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    $averageWeightKg = ($line['average_weight_kg'] ?? null)
                        ?: ($weights[$line['fish_type']]['average_weight_kg'] ?? null)
                        ?: 0.001;

                    $operation = $this->recordClosureLoss(
                        $pond,
                        $request->user()->id,
                        $line['fish_type'],
                        $qty,
                        (float) $averageWeightKg,
                        $closedOn,
                        'Havza yopildi — qoldiq taxminiy baliq (aniq sababi noma’lum)'
                    );

                    $adjustments['losses'][] = [
                        'fish_type' => $line['fish_type'],
                        'quantity' => $qty,
                        'operation_id' => $operation->id,
                        'reason' => 'early_remaining',
                    ];
                }
            } else {
                $types = collect(FishTypes::all())
                    ->merge($estimated->pluck('fish_type'))
                    ->merge($actualLines->keys())
                    ->unique()
                    ->values();

                foreach ($types as $fishType) {
                    $estimatedLine = $estimated->firstWhere('fish_type', $fishType);
                    $estimatedQty = (int) ($estimatedLine['count'] ?? 0);
                    $actualQty = (int) ($actualLines[$fishType]['quantity'] ?? 0);

                    if ($estimatedQty > $actualQty) {
                        $lossQty = $estimatedQty - $actualQty;
                        $averageWeightKg = ($estimatedLine['average_weight_kg'] ?? null)
                            ?: ($weights[$fishType]['average_weight_kg'] ?? null)
                            ?: 0.001;

                        $operation = $this->recordClosureLoss(
                            $pond,
                            $request->user()->id,
                            $fishType,
                            $lossQty,
                            (float) $averageWeightKg,
                            $closedOn,
                            'Kol tugatish — taxmindan kam chiqdi (aniq sababi noma’lum)'
                        );

                        $adjustments['losses'][] = [
                            'fish_type' => $fishType,
                            'quantity' => $lossQty,
                            'operation_id' => $operation->id,
                            'reason' => 'drain_shortage',
                            'estimated' => $estimatedQty,
                            'actual' => $actualQty,
                        ];
                    } elseif ($actualQty > $estimatedQty) {
                        $adjustments['surplus'][] = [
                            'fish_type' => $fishType,
                            'estimated' => $estimatedQty,
                            'actual' => $actualQty,
                            'surplus' => $actualQty - $estimatedQty,
                            'actual_weight_kg' => $actualLines[$fishType]['total_weight_kg'] ?? null,
                        ];
                    }
                }
            }

            $pond->refresh();
            $remaining = $this->buildEstimatedStockSnapshot($pond);
            foreach ($remaining as $line) {
                $qty = (int) ($line['count'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $averageWeightKg = ($line['average_weight_kg'] ?? null) ?: 0.001;
                $operation = $this->recordClosureLoss(
                    $pond,
                    $request->user()->id,
                    $line['fish_type'],
                    $qty,
                    (float) $averageWeightKg,
                    $closedOn,
                    'Havza yopish — qoldiq tozalash'
                );

                $adjustments['losses'][] = [
                    'fish_type' => $line['fish_type'],
                    'quantity' => $qty,
                    'operation_id' => $operation->id,
                    'reason' => 'cleanup',
                ];
            }

            $pond->current_stock = 0;
            $pond->closed_at = Carbon::parse($closedOn)->endOfDay();
            $pond->status = 'inactive';
            $pond->save();

            $closure = PondClosure::create([
                'pond_id' => $pond->id,
                'user_id' => $request->user()->id,
                'mode' => $data['mode'],
                'closed_on' => $closedOn,
                'estimated_stock' => $estimated->values()->all(),
                'actual_stock' => $actualLines->map(fn ($row, $type) => array_merge(['fish_type' => $type], $row))->values()->all(),
                'adjustments' => $adjustments,
                'summary_snapshot' => $summarySnapshot,
                'notes' => $data['notes'] ?? null,
            ]);

            return $closure;
        });

        $this->logActivity(
            $pond,
            $request->user()->id,
            'pond_closed',
            'Havza yopildi',
            $pond->name.' havzasi yakuniy hisobot bilan yopildi',
            [
                'mode' => $closure->mode,
                'closed_on' => $closure->closed_on?->toDateString(),
            ],
            $closure->closed_on
        );

        return response()->json([
            'closure' => $closure->load('user:id,name'),
            'pond' => $pond->fresh()->only(['id', 'name', 'closed_at', 'status', 'current_stock']),
        ], 201);
    }

    protected function authorizePond(Request $request, Pond $pond): void
    {
        PondAccess::authorizeDirectorOrOwner($request->user(), $pond);
    }

    protected function ensurePondOpen(Pond $pond): void
    {
        if ($pond->isClosed()) {
            throw ValidationException::withMessages([
                'pond' => ['Bu havza yopilgan. Ma’lumot faqat ko‘rish uchun saqlangan.'],
            ]);
        }

        if ($pond->pendingClosureRequest()->exists()) {
            throw ValidationException::withMessages([
                'pond' => ['Havza yopish arizasi kutilmoqda. BOSS javobigacha yangi amal bajarib bo‘lmaydi.'],
            ]);
        }
    }

    private function buildEstimatedStockSnapshot(Pond $pond): \Illuminate\Support\Collection
    {
        $labels = FishTypes::labels();
        $weights = $this->suggestedFishWeights($pond);
        $stockByType = $this->stockByFishType($pond);

        return collect(FishTypes::all())
            ->map(function (string $type) use ($labels, $weights, $stockByType) {
                $count = max(0, (int) ($stockByType[$type]['count'] ?? 0));
                $averageWeightKg = $weights[$type]['average_weight_kg'] ?? null;

                return [
                    'fish_type' => $type,
                    'label' => $labels[$type] ?? $type,
                    'count' => $count,
                    'average_weight_kg' => $averageWeightKg,
                    'mass_kg' => $averageWeightKg !== null ? round($count * $averageWeightKg, 3) : null,
                ];
            })
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();
    }

    private function recordClosureLoss(
        Pond $pond,
        int $userId,
        string $fishType,
        int $quantity,
        float $averageWeightKg,
        string $operatedAt,
        string $notes
    ): ?FishOperation {
        if ($quantity <= 0) {
            return null;
        }

        $totalWeightKg = round($quantity * $averageWeightKg, 3);

        if ($pond->current_stock < $quantity) {
            $quantity = max(0, (int) $pond->current_stock);
            $totalWeightKg = round($quantity * $averageWeightKg, 3);
        }

        if ($quantity <= 0) {
            return null;
        }

        $pond->current_stock -= $quantity;
        $pond->save();

        $operation = FishOperation::create([
            'pond_id' => $pond->id,
            'user_id' => $userId,
            'type' => 'loss',
            'fish_type' => $fishType,
            'quantity' => $quantity,
            'average_weight_kg' => $averageWeightKg,
            'total_weight_kg' => $totalWeightKg,
            'amount' => null,
            'title' => 'Havza yopish',
            'notes' => $notes,
            'operated_at' => $operatedAt,
        ]);

        $this->logActivity(
            $pond,
            $userId,
            'fish_loss',
            'Baliq poteryasi qayd etildi',
            $operation->quantity.' dona '.$fishType.' (havza yopish)',
            [
                'fish_type' => $fishType,
                'quantity' => $operation->quantity,
                'total_weight_kg' => $operation->total_weight_kg,
                'closure' => true,
            ],
            $operatedAt
        );

        return $operation;
    }

    protected function ensureBelongsToPond(Pond $pond, int $modelPondId): void
    {
        if ((int) $modelPondId !== (int) $pond->id) {
            abort(404);
        }
    }

    private function formatClosureRequest(PondClosureRequest $request): array
    {
        return [
            'id' => $request->id,
            'pond_id' => $request->pond_id,
            'status' => $request->status,
            'closed_on' => $request->closed_on?->toDateString(),
            'notes' => $request->notes,
            'confirmations' => $request->confirmations,
            'analysis_snapshot' => $request->analysis_snapshot,
            'review_notes' => $request->review_notes,
            'reviewed_at' => optional($request->reviewed_at)->toIso8601String(),
            'created_at' => optional($request->created_at)->toIso8601String(),
            'requester' => $request->relationLoaded('requester') && $request->requester
                ? $request->requester->only(['id', 'name'])
                : null,
            'reviewer' => $request->relationLoaded('reviewer') && $request->reviewer
                ? $request->reviewer->only(['id', 'name'])
                : null,
        ];
    }

    private function suggestedFishWeights(Pond $pond): array
    {
        $suggestions = [];

        foreach (FishTypes::all() as $type) {
            $fromMain = $this->latestMainReportWeight($pond, $type);
            if ($fromMain) {
                $suggestions[$type] = $fromMain;
                continue;
            }

            $weighted = $this->weightedAverageFromAdditions($pond, $type);
            if ($weighted) {
                $suggestions[$type] = $weighted;
            }
        }

        return $suggestions;
    }

    protected function logActivity(
        Pond $pond,
        int $userId,
        string $action,
        string $title,
        ?string $description = null,
        array $details = [],
        mixed $performedAt = null
    ): void {
        ActivityLog::create([
            'pond_id' => $pond->id,
            'user_id' => $userId,
            'action' => $action,
            'title' => $title,
            'description' => $description,
            'details' => $details,
            'performed_at' => $performedAt ?? now(),
        ]);
    }
}
