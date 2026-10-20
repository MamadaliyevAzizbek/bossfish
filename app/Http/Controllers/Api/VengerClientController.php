<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VengerClient;
use App\Models\VengerResult;
use App\Support\Districts;
use App\Support\FishTypes;
use App\Support\Regions;
use App\Support\VengerFeedTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VengerClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $region = trim((string) $request->query('region', ''));

        $query = VengerClient::query()
            ->withCount('results')
            ->with([
                'creator:id,name',
                'results' => fn ($rel) => $rel->latest('sold_on')->limit(1),
            ])
            ->latest();

        if ($region !== '') {
            $query->where('region', $region);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $digits = preg_replace('/\D+/', '', $q) ?: null;
            $query->where(function ($inner) use ($like, $digits) {
                $inner->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('region', 'like', $like)
                    ->orWhere('district', 'like', $like);
                if ($digits) {
                    $inner->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        $clients = $query->get()->map(fn (VengerClient $c) => $this->clientListPayload($c));

        return response()->json([
            'clients' => $clients,
            'stats' => [
                'clients_count' => (int) VengerClient::query()->count(),
                'results_count' => (int) VengerResult::query()->count(),
                'filtered_count' => $clients->count(),
            ],
        ]);
    }

    public function listPonds(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $base = VengerResult::query()->with(['client', 'fish', 'creator:id,name']);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $digits = preg_replace('/\D+/', '', $q) ?: null;
            $base->where(function ($inner) use ($like, $digits) {
                $inner->where('title', 'like', $like)
                    ->orWhereHas('client', function ($clientQ) use ($like, $digits) {
                        $clientQ->where('name', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('region', 'like', $like)
                            ->orWhere('district', 'like', $like);
                        if ($digits) {
                            $clientQ->orWhere('phone', 'like', '%'.$digits.'%');
                        }
                    });
            });
        }

        // Jarayonda: eng eski / yangilanganlari tepada (davom ettirish uchun)
        $open = (clone $base)
            ->where('status', VengerResult::STATUS_ACTIVE)
            ->orderBy('updated_at')
            ->get()
            ->map(fn (VengerResult $r) => $this->pondListPayload($r))
            ->values();

        // Yakunlangan: alohida arxiv — oxirgi yakunlanganlar tepada
        $final = (clone $base)
            ->where('status', VengerResult::STATUS_FINAL)
            ->orderByDesc('sold_on')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (VengerResult $r) => $this->pondListPayload($r))
            ->values();

        return response()->json([
            'open' => $open,
            'final' => $final,
            // eski clientlar uchun
            'ponds' => $open,
            'stats' => [
                'open' => $open->count(),
                'final' => $final->count(),
                'total' => $open->count() + $final->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateClient($request);

        $client = VengerClient::create([
            ...$data,
            'name' => VengerClient::fullName($data['first_name'], $data['last_name']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($this->clientDetailPayload($client->loadCount('results')), 201);
    }

    public function show(VengerClient $client): JsonResponse
    {
        $client->load(['results' => fn ($q) => $q->latest('sold_on'), 'creator:id,name']);

        return response()->json($this->clientDetailPayload($client, true));
    }

    public function update(Request $request, VengerClient $client): JsonResponse
    {
        $data = $this->validateClient($request, $client->id);
        $client->update([
            ...$data,
            'name' => VengerClient::fullName($data['first_name'], $data['last_name']),
        ]);

        return response()->json($this->clientDetailPayload($client->fresh()->loadCount('results')));
    }

    public function destroy(VengerClient $client): JsonResponse
    {
        $client->delete();

        return response()->json(['ok' => true]);
    }

    public function storeResult(Request $request, VengerClient $client): JsonResponse
    {
        [$meta, $fishRows] = $this->validateResult($request);

        $result = DB::transaction(function () use ($client, $request, $meta, $fishRows) {
            $result = $client->results()->create([
                ...$meta,
                'stocked_on' => $fishRows[0]['stocked_on'],
                'sold_on' => $fishRows[0]['sold_on'] ?? $fishRows[0]['stocked_on'],
                'stocked_pieces' => 0,
                'stocked_weight_kg' => 0,
                'sold_pieces' => 0,
                'sold_weight_kg' => 0,
                'created_by' => $request->user()->id,
            ]);

            $this->syncFishRows($result, $fishRows);

            return $result;
        });

        return response()->json($this->resultPayload($result->load(['client', 'fish', 'creator:id,name'])), 201);
    }

    public function showResult(VengerResult $result): JsonResponse
    {
        $result->load(['client', 'creator:id,name', 'fish']);

        return response()->json($this->resultPayload($result));
    }

    public function updateResult(Request $request, VengerResult $result): JsonResponse
    {
        [$meta, $fishRows] = $this->validateResult($request);

        DB::transaction(function () use ($result, $meta, $fishRows) {
            $result->update($meta);
            $this->syncFishRows($result, $fishRows);
        });

        return response()->json($this->resultPayload($result->fresh()->load(['client', 'creator:id,name', 'fish'])));
    }

    public function destroyResult(VengerResult $result): JsonResponse
    {
        $result->delete();

        return response()->json(['ok' => true]);
    }

    private function validateClient(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'address' => 'nullable|string|max:500',
            'region' => ['required', 'string', Rule::in(Regions::all())],
            'district' => 'required|string|max:120',
            'phone' => [
                'required',
                'string',
                'max:32',
                'regex:/^\+998\d{9}$/',
                Rule::unique('venger_clients', 'phone')->ignore($ignoreId),
            ],
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! Districts::isValid($data['region'], $data['district'])) {
            throw ValidationException::withMessages([
                'district' => ['Tanlangan tuman/shahar ushbu viloyatga tegishli emas.'],
            ]);
        }

        return $data;
    }

    /**
     * @return array{0: array, 1: list<array>}
     */
    private function validateResult(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'area_ha' => 'required|numeric|min:0.001|max:999999',
            'feed_kg' => 'nullable|numeric|min:0',
            'feeds' => 'nullable|array',
            'feeds.*.type' => ['required_with:feeds', 'string', Rule::in(VengerFeedTypes::accepted())],
            'feeds.*.kg' => 'nullable|numeric|min:0',
            'controls' => 'nullable|array',
            'controls.*.checked_on' => 'nullable|date',
            'controls.*.avg_g' => 'nullable|numeric|min:0',
            'controls.*.feed_kg' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'status' => ['nullable', 'string', Rule::in([VengerResult::STATUS_ACTIVE, VengerResult::STATUS_FINAL])],
            'fish' => 'required|array|min:1',
            'fish.*.fish_type' => ['required', 'string', Rule::in(FishTypes::all())],
            'fish.*.stocked_on' => 'required|date',
            'fish.*.stocked_pieces' => 'nullable|integer|min:0',
            'fish.*.stocked_weight_kg' => 'required|numeric|min:0.01',
            'fish.*.current_on' => 'nullable|date|after_or_equal:fish.*.stocked_on',
            'fish.*.current_pieces' => 'nullable|integer|min:0',
            'fish.*.current_weight_kg' => 'nullable|numeric|min:0',
            'fish.*.sold_on' => 'nullable|date|after_or_equal:fish.*.stocked_on',
            'fish.*.sold_pieces' => 'nullable|integer|min:0',
            'fish.*.sold_weight_kg' => 'nullable|numeric|min:0',
            'fish.*.loss_on' => 'nullable|date|after_or_equal:fish.*.stocked_on',
            'fish.*.loss_pieces' => 'nullable|integer|min:0',
            'fish.*.loss_weight_kg' => 'nullable|numeric|min:0',
            'fish.*.notes' => 'nullable|string|max:2000',
        ]);

        $types = collect($data['fish'])->pluck('fish_type');
        if ($types->count() !== $types->unique()->count()) {
            throw ValidationException::withMessages([
                'fish' => ['Har bir baliq turi faqat bir marta kiritiladi.'],
            ]);
        }

        $fishRows = [];
        $hasSold = false;
        foreach ($data['fish'] as $index => $row) {
            $soldPieces = (int) ($row['sold_pieces'] ?? 0);
            $lossPieces = (int) ($row['loss_pieces'] ?? 0);
            $stockedPieces = (int) ($row['stocked_pieces'] ?? 0);
            $soldWeight = (float) ($row['sold_weight_kg'] ?? 0);
            $lossWeight = (float) ($row['loss_weight_kg'] ?? 0);
            $currentWeight = isset($row['current_weight_kg']) && $row['current_weight_kg'] !== ''
                ? (float) $row['current_weight_kg']
                : null;

            if ($soldWeight > 0) {
                $hasSold = true;
            }

            if ($soldWeight > 0 && empty($row['sold_on'])) {
                throw ValidationException::withMessages([
                    "fish.$index.sold_on" => ['Sotuv sanasi majburiy.'],
                ]);
            }

            if ($currentWeight !== null && $currentWeight > 0 && empty($row['current_on'])) {
                throw ValidationException::withMessages([
                    "fish.$index.current_on" => ['Oraliq o‘lchov sanasi majburiy.'],
                ]);
            }

            $fishRows[] = [
                'fish_type' => $row['fish_type'],
                'stocked_on' => $row['stocked_on'],
                'stocked_pieces' => $stockedPieces,
                'stocked_weight_kg' => (float) $row['stocked_weight_kg'],
                'current_on' => $row['current_on'] ?? null,
                'current_pieces' => (int) ($row['current_pieces'] ?? 0) ?: null,
                'current_weight_kg' => $currentWeight,
                'sold_on' => $row['sold_on'] ?? null,
                'sold_pieces' => $soldPieces,
                'sold_weight_kg' => $soldWeight,
                'loss_on' => $row['loss_on'] ?? null,
                'loss_pieces' => $lossPieces,
                'loss_weight_kg' => $lossWeight,
                'notes' => $row['notes'] ?? null,
            ];
        }

        $status = $data['status'] ?? VengerResult::STATUS_ACTIVE;
        if ($status === VengerResult::STATUS_FINAL && ! $hasSold) {
            throw ValidationException::withMessages([
                'status' => ['Yakunlash uchun kamida bitta turda sotuv massasini yozing.'],
            ]);
        }
        if ($hasSold && ($data['status'] ?? null) === null) {
            $status = VengerResult::STATUS_FINAL;
        }

        $feeds = [];
        $seenFeedTypes = [];
        foreach ($data['feeds'] ?? [] as $index => $row) {
            $type = VengerFeedTypes::normalize($row['type'] ?? null);
            if (! $type) {
                throw ValidationException::withMessages([
                    "feeds.$index.type" => ['Yem turi Ekstruder yoki Granula bo‘lishi kerak.'],
                ]);
            }
            if (isset($seenFeedTypes[$type])) {
                continue;
            }
            $kg = round((float) ($row['kg'] ?? 0), 2);
            if ($kg <= 0) {
                continue;
            }
            $seenFeedTypes[$type] = true;
            $feeds[] = [
                'type' => $type,
                'kg' => $kg,
                'label' => VengerFeedTypes::label($type),
            ];
        }

        $controls = [];
        foreach ($data['controls'] ?? [] as $row) {
            if (empty($row['checked_on']) || (float) ($row['avg_g'] ?? 0) <= 0) {
                continue;
            }
            $controls[] = [
                'checked_on' => $row['checked_on'],
                'avg_g' => round((float) $row['avg_g'], 1),
                'feed_kg' => round((float) ($row['feed_kg'] ?? 0), 2),
            ];
        }
        usort($controls, fn ($a, $b) => strcmp($a['checked_on'], $b['checked_on']));

        $feedsTotal = round(collect($feeds)->sum('kg'), 2);
        $requestFeedKg = (float) ($data['feed_kg'] ?? 0);

        // Yem faqat yuqoridagi turlar (yoki umumiy feed_kg) — kontrol yemi hisobga olinmaydi
        $feedKg = $feedsTotal > 0 ? $feedsTotal : $requestFeedKg;

        // Eng so‘nggi kontrolni sazan current_* ga yozib qo‘yamiz (oraliq natija).
        // Sotuv bor bo‘lsa — yakuniy massa sotuvdan, kontrol faqat tarix.
        if (! empty($controls) && ! $hasSold) {
            $latest = end($controls);
            foreach ($fishRows as $i => $fishRow) {
                if (($fishRow['fish_type'] ?? '') !== 'sazan') {
                    continue;
                }
                $pieces = max((int) ($fishRow['stocked_pieces'] ?? 0), 0);
                $estKg = $pieces > 0 ? round($pieces * ((float) $latest['avg_g'] / 1000), 2) : null;
                $fishRows[$i]['current_on'] = $latest['checked_on'];
                $fishRows[$i]['current_pieces'] = $pieces ?: null;
                $fishRows[$i]['current_weight_kg'] = $estKg;
            }
        }

        $meta = [
            'title' => $data['title'] ?? null,
            'area_ha' => (float) $data['area_ha'],
            'feed_kg' => $feedKg,
            'feeds' => $feeds ?: null,
            'controls' => $controls ?: null,
            'notes' => $data['notes'] ?? null,
            'status' => $status,
        ];

        return [$meta, $fishRows];
    }

    private function syncFishRows(VengerResult $result, array $fishRows): void
    {
        $result->fish()->delete();

        foreach ($fishRows as $row) {
            $result->fish()->create($row);
        }

        $result->unsetRelation('fish');
        $result->syncAggregatesFromFish();
    }

    private function pondListPayload(VengerResult $result): array
    {
        $metrics = $result->metrics();
        $phase = $metrics['phase'] ?? 'stocked';
        $workflow = $result->status === VengerResult::STATUS_FINAL
            ? 'final'
            : ($phase === 'mid' ? 'mid' : 'started');

        $result->loadMissing('creator:id,name');

        return [
            'id' => $result->id,
            'title' => $result->title,
            'status' => $result->status,
            'workflow' => $workflow,
            'phase' => $phase,
            'area_ha' => $result->area_ha,
            'stocked_on' => optional($result->stocked_on)?->toDateString(),
            'sold_on' => optional($result->sold_on)?->toDateString(),
            'feed_kg' => $result->feed_kg,
            'feeds' => $result->feeds ?? [],
            'client' => $result->client ? [
                'id' => $result->client->id,
                'name' => $result->client->name,
                'region' => $result->client->region,
                'district' => $result->client->district,
                'phone' => $result->client->phone,
            ] : null,
            'staff' => $result->creator ? [
                'id' => $result->creator->id,
                'name' => $result->creator->name,
            ] : null,
            'metrics' => [
                'fcr' => $metrics['fcr'] ?? null,
                'phase' => $phase,
                'sazan' => $metrics['sazan'] ?? null,
            ],
            'updated_at' => optional($result->updated_at)?->toIso8601String(),
        ];
    }

    private function clientListPayload(VengerClient $client): array
    {
        $latest = $client->results->first();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'address' => $client->address,
            'region' => $client->region,
            'district' => $client->district,
            'phone' => $client->phone,
            'notes' => $client->notes,
            'results_count' => (int) ($client->results_count ?? $client->results()->count()),
            'latest_result' => $latest ? [
                'id' => $latest->id,
                'sold_on' => optional($latest->sold_on)?->toDateString(),
                'area_ha' => $latest->area_ha,
                'fcr' => $latest->metrics()['fcr'],
            ] : null,
            'staff' => $client->creator ? [
                'id' => $client->creator->id,
                'name' => $client->creator->name,
            ] : null,
            'created_at' => optional($client->created_at)?->toIso8601String(),
        ];
    }

    private function clientDetailPayload(VengerClient $client, bool $withResults = false): array
    {
        $payload = [
            'id' => $client->id,
            'name' => $client->name,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'address' => $client->address,
            'region' => $client->region,
            'district' => $client->district,
            'phone' => $client->phone,
            'notes' => $client->notes,
            'results_count' => (int) ($client->results_count ?? $client->results()->count()),
            'created_by' => $client->creator?->only(['id', 'name']),
            'created_at' => optional($client->created_at)?->toIso8601String(),
        ];

        if ($withResults) {
            $client->loadMissing(['results.fish']);
            $payload['results'] = $client->results->map(fn (VengerResult $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'status' => $r->status,
                'area_ha' => $r->area_ha,
                'stocked_on' => optional($r->stocked_on)?->toDateString(),
                'sold_on' => optional($r->sold_on)?->toDateString(),
                'stocked_pieces' => $r->stocked_pieces,
                'sold_pieces' => $r->sold_pieces,
                'feed_kg' => $r->feed_kg,
                'feeds' => $r->feeds ?? [],
                'sold_weight_kg' => $r->sold_weight_kg,
                'fish_types' => $r->fish->pluck('fish_type')->values(),
                'metrics' => $r->metrics(),
            ])->values();
        }

        return $payload;
    }

    private function resultPayload(VengerResult $result): array
    {
        $result->loadMissing(['fish', 'client', 'creator:id,name']);
        $metrics = $result->metrics();

        return [
            'id' => $result->id,
            'title' => $result->title,
            'status' => $result->status,
            'area_ha' => $result->area_ha,
            'stocked_on' => $metrics['stocked_on'] ?? optional($result->stocked_on)?->toDateString(),
            'stocked_pieces' => $metrics['stocked_pieces'] ?? $result->stocked_pieces,
            'stocked_weight_kg' => $metrics['stocked_weight_kg'] ?? $result->stocked_weight_kg,
            'feed_kg' => $result->feed_kg,
            'feeds' => collect($result->feeds ?? [])
                ->map(function ($row) {
                    $type = VengerFeedTypes::normalize($row['type'] ?? null);

                    return $type ? [
                        'type' => $type,
                        'kg' => isset($row['kg']) ? (float) $row['kg'] : 0,
                        'label' => VengerFeedTypes::label($type),
                    ] : null;
                })
                ->filter()
                ->unique('type')
                ->values()
                ->all(),
            'controls' => $metrics['controls'] ?? $result->controlsList()->all(),
            'sold_on' => $metrics['sold_on'] ?? optional($result->sold_on)?->toDateString(),
            'sold_pieces' => $metrics['sold_pieces'] ?? $result->sold_pieces,
            'sold_weight_kg' => $metrics['sold_weight_kg'] ?? $result->sold_weight_kg,
            'loss_pieces' => $metrics['loss_pieces'] ?? 0,
            'loss_weight_kg' => $metrics['loss_weight_kg'] ?? 0,
            'notes' => $result->notes,
            'fish' => $metrics['fish'] ?? [],
            'created_by' => $result->creator?->only(['id', 'name']),
            'client' => $result->client ? [
                'id' => $result->client->id,
                'name' => $result->client->name,
                'first_name' => $result->client->first_name,
                'last_name' => $result->client->last_name,
                'address' => $result->client->address,
                'region' => $result->client->region,
                'district' => $result->client->district,
                'phone' => $result->client->phone,
            ] : null,
            'metrics' => $metrics,
            'created_at' => optional($result->created_at)?->toIso8601String(),
            'updated_at' => optional($result->updated_at)?->toIso8601String(),
        ];
    }
}

