<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClientSubscription;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\PondClosureRequest;
use App\Models\PondCreateRequest;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\PondClosureService;
use App\Support\Districts;
use App\Support\Regions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class OwnerCabinetController extends Controller
{
    public function __construct(
        private readonly PondClosureService $pondClosure,
        private readonly DirectorCabinetController $directorCabinet,
    ) {}
    public function dashboard(Request $request): JsonResponse
    {
        $expiringDirectors = User::query()
            ->where('role', User::ROLE_DIRECTOR)
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '>', now())
            ->where('access_expires_at', '<=', now()->addDays(User::ACCESS_EXPIRING_DAYS))
            ->orderBy('access_expires_at')
            ->get(['id', 'name', 'phone', 'access_expires_at'])
            ->map(fn (User $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'phone' => $d->phone,
                'access_days_left' => $d->access_days_left,
                'access_expires_at' => optional($d->access_expires_at)->toIso8601String(),
            ]);

        $directorsCount = User::where('role', User::ROLE_DIRECTOR)->count();
        $workersCount = User::where('role', User::ROLE_WORKER)->count();

        $byRegion = Pond::query()
            ->select('region')
            ->selectRaw('COUNT(*) as ponds_count')
            ->selectRaw('COUNT(DISTINCT director_id) as directors_count')
            ->selectRaw('COALESCE(SUM(area_m2), 0) as area_ha')
            ->selectRaw('COALESCE(SUM(current_stock), 0) as fish_count')
            ->groupBy('region')
            ->orderBy('region')
            ->get()
            ->map(function ($row) {
                $coords = Regions::latLng($row->region);

                return [
                    'region' => $row->region,
                    'lat' => $coords['lat'] ?? null,
                    'lng' => $coords['lng'] ?? null,
                    'ponds_count' => (int) $row->ponds_count,
                    'directors_count' => (int) $row->directors_count,
                    'area_ha' => round((float) $row->area_ha, 2),
                    'fish_count' => (int) $row->fish_count,
                ];
            })
            ->values();

        $mapPonds = Pond::query()
            ->with('director:id,name,phone')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'region', 'latitude', 'longitude', 'area_m2', 'current_stock', 'director_id', 'status'])
            ->map(fn (Pond $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'region' => $p->region,
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'area_m2' => (float) $p->area_m2,
                'current_stock' => (int) $p->current_stock,
                'status' => $p->status,
                'director' => $p->director ? [
                    'id' => $p->director->id,
                    'name' => $p->director->name,
                    'phone' => $p->director->phone,
                ] : null,
            ])
            ->values();

        $subscriptions = ClientSubscription::query()->get();
        foreach ($subscriptions as $sub) {
            $sub->refreshExpiredStatus();
        }

        $clientsDue = $subscriptions->filter(fn (ClientSubscription $s) => $s->needsCheck())->count();
        $clientsAttention = $subscriptions->where('status', ClientSubscription::STATUS_ATTENTION)->count();
        $clientsExpiring = $subscriptions->filter(fn (ClientSubscription $s) => $s->isExpiringSoon())->count();
        $clientsWithoutPlan = max(0, $directorsCount - $subscriptions->count());

        return response()->json([
            'directors_count' => $directorsCount,
            'workers_count' => $workersCount,
            'users_count' => $directorsCount + $workersCount,
            'farms_count' => Farm::count(),
            'ponds_count' => Pond::count(),
            'active_ponds_count' => Pond::where('status', 'active')->count(),
            'total_area_ha' => round((float) Pond::sum('area_m2'), 2),
            'total_fish_count' => (int) Pond::sum('current_stock'),
            'by_region' => $byRegion,
            'map_ponds' => $mapPonds,
            'expiring_directors' => $expiringDirectors,
            'clients' => [
                'with_plan' => $subscriptions->count(),
                'without_plan' => $clientsWithoutPlan,
                'cabinet' => $subscriptions->where('plan', ClientSubscription::PLAN_CABINET)->count(),
                'control' => $subscriptions->where('plan', ClientSubscription::PLAN_CONTROL)->count(),
                'individual' => $subscriptions->where('plan', ClientSubscription::PLAN_INDIVIDUAL)->count(),
                'due_checks' => $clientsDue,
                'attention' => $clientsAttention,
                'expiring' => $clientsExpiring,
            ],
        ]);
    }

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

    public function directors(Request $request): JsonResponse
    {
        $region = $request->query('region');
        $q = trim((string) $request->query('q', ''));
        $plan = $request->query('plan');
        $subStatus = $request->query('sub_status');
        $due = $request->boolean('due');
        $noPlan = $request->boolean('no_plan');
        $expiring = $request->boolean('expiring');

        $query = User::query()
            ->where('role', User::ROLE_DIRECTOR)
            ->with([
                'directedPonds:id,name,region,district,director_id,area_m2,latitude,longitude,farm_id,status',
                'directedFarms:id,name,region,director_id',
                'clientSubscription',
            ])
            ->withCount(['directedPonds', 'directedFarms']);

        if ($region) {
            $query->whereHas('directedPonds', fn ($q) => $q->where('region', $region));
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $digits = preg_replace('/\D+/', '', $q) ?: null;
            $query->where(function ($inner) use ($like, $digits) {
                $inner->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if ($digits) {
                    $inner->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        if ($noPlan) {
            $query->whereDoesntHave('clientSubscription');
        } elseif ($plan && in_array($plan, ClientSubscription::PLANS, true)) {
            $query->whereHas('clientSubscription', fn ($q) => $q->where('plan', $plan));
        }

        if ($subStatus && in_array($subStatus, ClientSubscription::STATUSES, true)) {
            $query->whereHas('clientSubscription', fn ($q) => $q->where('status', $subStatus));
        }

        if ($due) {
            $query->whereHas('clientSubscription', function ($q) {
                $q->whereIn('plan', [ClientSubscription::PLAN_CONTROL, ClientSubscription::PLAN_INDIVIDUAL])
                    ->where(function ($inner) {
                        $inner->whereNull('next_check_at')
                            ->orWhereDate('next_check_at', '<=', now()->toDateString());
                    });
            });
        }

        if ($expiring) {
            $until = now()->addDays(User::ACCESS_EXPIRING_DAYS);
            $query->where(function ($outer) use ($until) {
                $outer->where(function ($access) use ($until) {
                    $access->whereNotNull('access_expires_at')
                        ->where('access_expires_at', '>', now())
                        ->where('access_expires_at', '<=', $until);
                })->orWhereHas('clientSubscription', function ($sub) use ($until) {
                    $sub->whereNotNull('ends_at')
                        ->whereDate('ends_at', '>=', now()->toDateString())
                        ->whereDate('ends_at', '<=', $until->toDateString());
                });
            });
        }

        $directors = $query
            ->orderBy('name')
            ->get();

        $farmIds = $directors->flatMap(fn (User $d) => $d->directedFarms->pluck('id'))->unique()->values();
        $pondIds = $directors->flatMap(fn (User $d) => $d->directedPonds->pluck('id'))->unique()->values();

        $expensesByFarm = $farmIds->isEmpty()
            ? collect()
            : Expense::query()
                ->select('farm_id', DB::raw('SUM(amount) as total'))
                ->whereIn('farm_id', $farmIds)
                ->groupBy('farm_id')
                ->pluck('total', 'farm_id');

        $salesByPond = $pondIds->isEmpty()
            ? collect()
            : FishOperation::query()
                ->select('pond_id', DB::raw('SUM(amount) as total'))
                ->whereIn('pond_id', $pondIds)
                ->where('type', 'sale')
                ->groupBy('pond_id')
                ->pluck('total', 'pond_id');

        $rows = $directors->map(function (User $d) use ($expensesByFarm, $salesByPond) {
            $row = $d->toArray();
            $row['self_registered'] = $d->created_by === null;
            $row['password'] = $d->password_plain;
            $row['regions'] = collect()
                ->merge($d->directedPonds->pluck('region'))
                ->merge($d->directedFarms->pluck('region'))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $row['ponds_total_area_m2'] = round((float) $d->directedPonds->sum('area_m2'), 2);
            $row['directed_farms'] = $d->directedFarms;
            $row['map_ponds'] = $d->directedPonds
                ->filter(fn ($p) => $p->latitude !== null && $p->longitude !== null)
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'region' => $p->region,
                    'district' => $p->district,
                    'latitude' => (float) $p->latitude,
                    'longitude' => (float) $p->longitude,
                    'area_m2' => (float) $p->area_m2,
                    'current_stock' => null,
                    'director' => [
                        'id' => $d->id,
                        'name' => $d->name,
                        'phone' => $d->phone,
                    ],
                ])
                ->values()
                ->all();
            $row['located_ponds_count'] = $d->directedPonds
                ->filter(fn ($p) => filled($p->district))
                ->count();
            $row['districts'] = $d->directedPonds
                ->pluck('district')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $totalExpenses = round((float) $d->directedFarms->sum(
                fn ($farm) => (float) ($expensesByFarm[$farm->id] ?? 0)
            ), 2);
            $totalSales = round((float) $d->directedPonds->sum(
                fn ($pond) => (float) ($salesByPond[$pond->id] ?? 0)
            ), 2);
            $profit = round($totalSales - $totalExpenses, 2);

            $row['total_expenses'] = $totalExpenses;
            $row['total_sales'] = $totalSales;
            $row['profit'] = $profit;

            $sub = $d->clientSubscription;
            if ($sub) {
                $sub->refreshExpiredStatus();
                $row['subscription'] = [
                    'id' => $sub->id,
                    'plan' => $sub->plan,
                    'billing_period' => $sub->billing_period ?: ClientSubscription::PERIOD_YEAR,
                    'price_amount' => $sub->price_amount,
                    'starts_at' => optional($sub->starts_at)?->toDateString(),
                    'ends_at' => optional($sub->ends_at)?->toDateString(),
                    'next_check_at' => optional($sub->next_check_at)?->toDateString(),
                    'check_interval_days' => $sub->check_interval_days,
                    'last_checked_at' => optional($sub->last_checked_at)?->toIso8601String(),
                    'status' => $sub->status,
                    'attention_note' => $sub->attention_note,
                    'notes' => $sub->notes,
                    'needs_check' => $sub->needsCheck(),
                    'expiring_soon' => $sub->isExpiringSoon(),
                ];
            } else {
                $row['subscription'] = null;
            }

            unset($row['client_subscription']);

            return $row;
        });

        return response()->json($rows);
    }

    public function storeDirector(Request $request): JsonResponse
    {
        $request->merge([
            'phone' => \App\Support\Phone::normalize((string) $request->input('phone', '')),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^\+998\d{9}$/', 'unique:users,phone'],
            'password' => 'required|string|min:4|max:32',
            'access_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $autoPlan = ! isset($data['access_days']);
        $periodDays = ClientSubscription::periodDays(ClientSubscription::PERIOD_YEAR);
        $accessExpiresAt = isset($data['access_days'])
            ? now()->addDays((int) $data['access_days'])
            : now()->addDays($periodDays)->endOfDay();

        $director = User::create([
            'name' => $data['name'],
            'email' => null,
            'phone' => $data['phone'],
            'password' => $data['password'],
            'password_plain' => $data['password'],
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'access_expires_at' => $accessExpiresAt,
        ]);

        $subscription = null;
        if ($autoPlan) {
            $startsAt = now()->toDateString();
            $endsAt = now()->addDays($periodDays)->toDateString();
            $subscription = ClientSubscription::create([
                'director_id' => $director->id,
                'farm_id' => null,
                'plan' => ClientSubscription::PLAN_CABINET,
                'billing_period' => ClientSubscription::PERIOD_YEAR,
                'price_amount' => ClientSubscription::defaultPriceForPlan(
                    ClientSubscription::PLAN_CABINET,
                    ClientSubscription::PERIOD_YEAR
                ),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'next_check_at' => null,
                'check_interval_days' => 0,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
            ]);
            $director->access_expires_at = $subscription->ends_at->copy()->endOfDay();
            $director->save();
        }

        $payload = $this->credentialPayload(
            $director->fresh()->load([
                'directedPonds:id,name,region,director_id',
                'directedFarms:id,name,region,director_id',
                'clientSubscription',
            ])
        );

        if ($subscription) {
            $sub = $director->fresh()->clientSubscription;
            $payload['subscription'] = $sub ? [
                'id' => $sub->id,
                'plan' => $sub->plan,
                'billing_period' => $sub->billing_period ?: ClientSubscription::PERIOD_YEAR,
                'price_amount' => $sub->price_amount,
                'starts_at' => optional($sub->starts_at)?->toDateString(),
                'ends_at' => optional($sub->ends_at)?->toDateString(),
                'next_check_at' => optional($sub->next_check_at)?->toDateString(),
                'check_interval_days' => $sub->check_interval_days,
                'status' => $sub->status,
                'attention_note' => $sub->attention_note,
                'notes' => $sub->notes,
            ] : null;
        } else {
            $payload['subscription'] = null;
        }

        return response()->json($payload, 201);
    }

    public function updateDirector(Request $request, User $director): JsonResponse
    {
        if ($director->role !== User::ROLE_DIRECTOR) {
            abort(404);
        }

        if ($request->filled('phone')) {
            $request->merge([
                'phone' => \App\Support\Phone::normalize((string) $request->input('phone')),
            ]);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => ['sometimes', 'regex:/^\+998\d{9}$/', Rule::unique('users', 'phone')->ignore($director->id)],
            'password' => 'nullable|string|min:4|max:32',
            'is_active' => 'sometimes|boolean',
            'access_days' => 'nullable|integer|min:1|max:3650',
            'extend_access_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $payload = collect($data)->only(['name', 'phone', 'is_active'])->all();
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['password_plain'] = $data['password'];
        }

        $director->update($payload);

        if (! empty($data['access_days'])) {
            $director->grantAccessDays((int) $data['access_days'], false);
        } elseif (! empty($data['extend_access_days'])) {
            $director->grantAccessDays((int) $data['extend_access_days'], true);
        }

        return response()->json($this->credentialPayload($director->fresh()->load('directedPonds:id,name,region,director_id')));
    }

    public function farms(): JsonResponse
    {
        $farms = Farm::query()
            ->withCount('ponds')
            ->with('director:id,name,phone')
            ->latest()
            ->get();

        return response()->json($farms);
    }

    public function storeFarm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'region' => ['required', 'string', Rule::in(Regions::all())],
            'director_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_DIRECTOR)),
            ],
            'notes' => 'nullable|string',
        ]);

        $alreadyHasFarm = Farm::query()
            ->where('director_id', $data['director_id'])
            ->exists();

        if ($alreadyHasFarm) {
            throw ValidationException::withMessages([
                'director_id' => ['Har bir direktor faqat bitta xo‘jalikka ega bo‘lishi mumkin.'],
            ]);
        }

        $farm = Farm::create([
            'name' => $data['name'],
            'region' => $data['region'],
            'director_id' => $data['director_id'],
            'created_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ]);

        // Direktorning hodimlarini yangi xo‘jalikka avtomatik bog‘lash
        User::query()
            ->where('role', User::ROLE_WORKER)
            ->where('director_id', $data['director_id'])
            ->each(function (User $worker) use ($farm) {
                $worker->assignedFarms()->sync([$farm->id]);
            });

        return response()->json(
            $farm->load('director:id,name,phone')->loadCount('ponds'),
            201
        );
    }

    public function workers(): JsonResponse
    {
        $workers = User::query()
            ->where('role', User::ROLE_WORKER)
            ->with([
                'assignedFarms:id,name,region,director_id',
                'director:id,name,phone,access_expires_at,is_active',
            ])
            ->latest()
            ->get()
            ->map(fn (User $worker) => $this->workerCredentialPayload($worker));

        return response()->json($workers);
    }

    public function storeWorker(Request $request): JsonResponse
    {
        $request->merge([
            'phone' => \App\Support\Phone::normalize((string) $request->input('phone', '')),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^\+998\d{9}$/', 'unique:users,phone'],
            'password' => 'required|string|min:4|max:32',
            'director_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_DIRECTOR)),
            ],
        ]);

        $worker = User::create([
            'name' => $data['name'],
            'email' => null,
            'phone' => $data['phone'],
            'password' => $data['password'],
            'password_plain' => $data['password'],
            'role' => User::ROLE_WORKER,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'director_id' => $data['director_id'],
        ]);

        $this->syncWorkerFarmToDirector($worker, (int) $data['director_id']);

        return response()->json(
            $this->workerCredentialPayload(
                $worker->fresh()->load(['assignedFarms:id,name,region,director_id', 'director:id,name,phone,access_expires_at'])
            ),
            201
        );
    }

    public function updateWorker(Request $request, User $worker): JsonResponse
    {
        if ($worker->role !== User::ROLE_WORKER) {
            abort(404);
        }

        if ($request->filled('phone')) {
            $request->merge([
                'phone' => \App\Support\Phone::normalize((string) $request->input('phone')),
            ]);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => ['sometimes', 'regex:/^\+998\d{9}$/', Rule::unique('users', 'phone')->ignore($worker->id)],
            'password' => 'nullable|string|min:4|max:32',
            'is_active' => 'sometimes|boolean',
            'director_id' => [
                'sometimes',
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_DIRECTOR)),
            ],
        ]);

        $payload = collect($data)->only(['name', 'phone', 'is_active', 'director_id'])->all();
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['password_plain'] = $data['password'];
        }
        $worker->update($payload);

        if (array_key_exists('director_id', $data)) {
            $this->syncWorkerFarmToDirector($worker, (int) $data['director_id']);
        }

        $worker = $worker->fresh()->load([
            'assignedFarms:id,name,region,director_id',
            'director:id,name,phone,access_expires_at',
        ]);

        return response()->json($this->workerCredentialPayload($worker));
    }

    private function syncWorkerFarmToDirector(User $worker, int $directorId): void
    {
        $farmId = Farm::query()
            ->where('director_id', $directorId)
            ->orderBy('id')
            ->value('id');

        if ($farmId) {
            $worker->assignedFarms()->sync([(int) $farmId]);
        } else {
            $worker->assignedFarms()->detach();
        }
    }

    public function security(Request $request): JsonResponse
    {
        $owner = $request->user();

        $directors = User::query()
            ->where('role', User::ROLE_DIRECTOR)
            ->with([
                'directedPonds:id,name,region,director_id',
                'directedFarms:id,name,region,director_id',
            ])
            ->latest()
            ->get()
            ->map(fn (User $u) => $this->credentialPayload($u));

        $workers = User::query()
            ->where('role', User::ROLE_WORKER)
            ->with([
                'assignedFarms:id,name,region,director_id',
                'director:id,name,phone,access_expires_at,is_active',
            ])
            ->latest()
            ->get()
            ->map(fn (User $u) => $this->workerCredentialPayload($u));

        $expiringDirectors = $directors
            ->filter(fn (array $d) => ($d['access_expiring_soon'] ?? false) === true)
            ->values();

        return response()->json([
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'password' => $owner->password_plain,
                'login_url' => url('/egasi/kirish'),
            ],
            'directors' => $directors,
            'workers' => $workers,
            'expiring_directors' => $expiringDirectors,
        ]);
    }

    public function vengerOverview(): JsonResponse
    {
        $openCount = \App\Models\VengerResult::query()
            ->where('status', \App\Models\VengerResult::STATUS_ACTIVE)
            ->count();
        $finalCount = \App\Models\VengerResult::query()
            ->where('status', \App\Models\VengerResult::STATUS_FINAL)
            ->count();

        $staffUsers = User::query()
            ->where('role', User::ROLE_VENGER)
            ->latest()
            ->get();

        $staff = $staffUsers->map(function (User $u) {
            $payload = $this->vengerStaffPayload($u);
            $payload['clients_count'] = \App\Models\VengerClient::query()
                ->where('created_by', $u->id)
                ->count();
            $payload['results_count'] = \App\Models\VengerResult::query()
                ->where('created_by', $u->id)
                ->count();
            $payload['open_count'] = \App\Models\VengerResult::query()
                ->where('created_by', $u->id)
                ->where('status', \App\Models\VengerResult::STATUS_ACTIVE)
                ->count();
            $payload['final_count'] = \App\Models\VengerResult::query()
                ->where('created_by', $u->id)
                ->where('status', \App\Models\VengerResult::STATUS_FINAL)
                ->count();

            return $payload;
        });

        $recentResults = \App\Models\VengerResult::query()
            ->with(['client:id,name,phone', 'creator:id,name,email'])
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->map(fn (\App\Models\VengerResult $r) => [
                'id' => $r->id,
                'kind' => 'result',
                'title' => $r->title ?: ('#'.$r->id),
                'status' => $r->status,
                'client' => $r->client ? [
                    'id' => $r->client->id,
                    'name' => $r->client->name,
                    'phone' => $r->client->phone,
                ] : null,
                'staff' => $r->creator ? [
                    'id' => $r->creator->id,
                    'name' => $r->creator->name,
                ] : null,
                'at' => optional($r->updated_at)?->toIso8601String(),
            ]);

        $recentClients = \App\Models\VengerClient::query()
            ->with(['creator:id,name'])
            ->withCount('results')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (\App\Models\VengerClient $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'phone' => $c->phone,
                'address' => $c->address,
                'region' => $c->region,
                'district' => $c->district,
                'results_count' => (int) $c->results_count,
                'staff' => $c->creator ? [
                    'id' => $c->creator->id,
                    'name' => $c->creator->name,
                ] : null,
                'created_at' => optional($c->created_at)?->toIso8601String(),
            ]);

        $unknown = 'Nomaʼlum';
        $allClients = \App\Models\VengerClient::query()
            ->get(['id', 'region', 'district']);

        $regionBuckets = [];
        $districtBuckets = [];
        foreach ($allClients as $client) {
            $region = trim((string) ($client->region ?? ''));
            $district = trim((string) ($client->district ?? ''));
            $regionLabel = $region !== '' ? $region : $unknown;
            $districtLabel = $district !== '' ? $district : $unknown;

            if (! isset($regionBuckets[$regionLabel])) {
                $regionBuckets[$regionLabel] = ['region' => $regionLabel, 'count' => 0];
            }
            $regionBuckets[$regionLabel]['count']++;

            $districtKey = $regionLabel.'|'.$districtLabel;
            if (! isset($districtBuckets[$districtKey])) {
                $districtBuckets[$districtKey] = [
                    'region' => $regionLabel,
                    'district' => $districtLabel,
                    'count' => 0,
                ];
            }
            $districtBuckets[$districtKey]['count']++;
        }

        $clientsByRegion = collect($regionBuckets)
            ->sortByDesc('count')
            ->values();

        $clientsByDistrict = collect($districtBuckets)
            ->sortByDesc('count')
            ->take(12)
            ->values();

        $allResults = \App\Models\VengerResult::query()
            ->with(['fish', 'client:id,region,district'])
            ->get();

        $fcrValues = [];
        $gainValues = [];
        $feedValues = [];
        $areaValues = [];
        $yieldValues = [];
        $feedTypeKg = ['extruder' => 0.0, 'granula' => 0.0];
        $yearBuckets = [];

        foreach ($allResults as $result) {
            $yearDate = $result->sold_on ?? $result->stocked_on ?? $result->created_at;
            $year = $yearDate ? (int) $yearDate->format('Y') : (int) now()->format('Y');
            if (! isset($yearBuckets[$year])) {
                $yearBuckets[$year] = ['year' => $year, 'open' => 0, 'final' => 0, 'total' => 0];
            }
            $yearBuckets[$year]['total']++;
            if ($result->status === \App\Models\VengerResult::STATUS_FINAL) {
                $yearBuckets[$year]['final']++;
            } elseif ($result->status === \App\Models\VengerResult::STATUS_ACTIVE) {
                $yearBuckets[$year]['open']++;
            }

            $metrics = $result->metrics();
            $areaValues[] = (float) $result->area_ha;
            if ((float) $result->feed_kg > 0) {
                $feedValues[] = (float) $result->feed_kg;
            }
            if (($metrics['fcr'] ?? null) !== null) {
                $fcrValues[] = (float) $metrics['fcr'];
            }
            $gain = $metrics['sazan']['biomass_gain_kg'] ?? null;
            if ($gain !== null) {
                $gainValues[] = (float) $gain;
            }
            if (($metrics['yield_per_ha_kg'] ?? null) !== null && (float) $metrics['yield_per_ha_kg'] > 0) {
                $yieldValues[] = (float) $metrics['yield_per_ha_kg'];
            }

            $feeds = $result->feeds ?? [];
            if ($feeds !== []) {
                foreach ($feeds as $feedRow) {
                    $type = \App\Support\VengerFeedTypes::normalize($feedRow['type'] ?? null);
                    $kg = (float) ($feedRow['kg'] ?? 0);
                    if ($type === \App\Support\VengerFeedTypes::EXTRUDER) {
                        $feedTypeKg['extruder'] += $kg;
                    } elseif ($type === \App\Support\VengerFeedTypes::GRANULA) {
                        $feedTypeKg['granula'] += $kg;
                    }
                }
            } elseif ((float) $result->feed_kg > 0) {
                // Legacy rows without typed feeds — skip type split
            }
        }

        ksort($yearBuckets);
        $resultsByYear = array_values($yearBuckets);

        $avg = static function (array $values, int $decimals = 2): ?float {
            if ($values === []) {
                return null;
            }

            return round(array_sum($values) / count($values), $decimals);
        };

        $analytics = [
            'clients_by_region' => $clientsByRegion,
            'clients_by_district' => $clientsByDistrict,
            'results_by_year' => $resultsByYear,
            'feed_types' => [
                [
                    'type' => 'extruder',
                    'label' => 'Ekstruder',
                    'kg' => round($feedTypeKg['extruder'], 1),
                ],
                [
                    'type' => 'granula',
                    'label' => 'Granula',
                    'kg' => round($feedTypeKg['granula'], 1),
                ],
            ],
            'averages' => [
                'fcr' => $avg($fcrValues, 2),
                'area_ha' => $avg($areaValues, 2),
                'yield_per_ha_kg' => $avg($yieldValues, 1),
                'completion_rate' => ($openCount + $finalCount) > 0
                    ? round(($finalCount / ($openCount + $finalCount)) * 100, 1)
                    : null,
            ],
            'totals' => [
                'feed_kg' => round(array_sum($feedValues), 1),
                'extruder_kg' => round($feedTypeKg['extruder'], 1),
                'granula_kg' => round($feedTypeKg['granula'], 1),
                'biomass_gain_kg' => round(array_sum($gainValues), 1),
                'area_ha' => round(array_sum($areaValues), 2),
                'regions_count' => $clientsByRegion->count(),
            ],
        ];

        return response()->json([
            'login_url' => url('/venger/kirish'),
            'login_path' => '/venger/kirish',
            'stats' => [
                'staff_count' => $staff->count(),
                'active_count' => $staff->where('is_active', true)->count(),
                'inactive_count' => $staff->where('is_active', false)->count(),
                'clients_count' => \App\Models\VengerClient::query()->count(),
                'results_count' => \App\Models\VengerResult::query()->count(),
                'ponds_open' => $openCount,
                'ponds_final' => $finalCount,
                'ponds_total' => $openCount + $finalCount,
            ],
            'analytics' => $analytics,
            'staff' => $staff,
            'recent_clients' => $recentClients,
            'recent_activity' => $recentResults,
        ]);
    }

    public function storeVengerStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:4|max:64',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
            'password_plain' => $data['password'],
            'role' => User::ROLE_VENGER,
            'phone' => null,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($this->vengerStaffPayload($user), 201);
    }

    public function updateVengerStaff(Request $request, User $vengerStaff): JsonResponse
    {
        if ($vengerStaff->role !== User::ROLE_VENGER) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($vengerStaff->id)],
            'password' => 'nullable|string|min:4|max:64',
            'is_active' => 'sometimes|boolean',
        ]);

        $payload = collect($data)->only(['name', 'is_active'])->all();
        if (array_key_exists('email', $data)) {
            $payload['email'] = mb_strtolower(trim($data['email']));
        }
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['password_plain'] = $data['password'];
        }

        $vengerStaff->update($payload);

        return response()->json($this->vengerStaffPayload($vengerStaff->fresh()));
    }

    public function destroyVengerStaff(User $vengerStaff): JsonResponse
    {
        if ($vengerStaff->role !== User::ROLE_VENGER) {
            abort(404);
        }

        $vengerStaff->tokens()->delete();
        $vengerStaff->delete();

        return response()->json(['ok' => true]);
    }

    private function vengerStaffPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password_plain,
            'is_active' => (bool) $user->is_active,
            'created_at' => optional($user->created_at)?->toIso8601String(),
            'login_url' => url('/venger/kirish'),
        ];
    }

    public function updateOwnerCredentials(Request $request): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($owner->id)],
            'password' => 'nullable|string|min:4|max:64',
        ]);

        $payload = collect($data)->only(['name', 'email'])->all();
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['password_plain'] = $data['password'];
        }

        $owner->update($payload);

        return response()->json([
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'password' => $owner->fresh()->password_plain,
            'login_url' => url('/egasi/kirish'),
        ]);
    }

    private function credentialPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'password' => $user->password_plain,
            'is_active' => $user->is_active,
            'access_expires_at' => optional($user->access_expires_at)?->toIso8601String(),
            'access_days_left' => $user->access_days_left,
            'access_expired' => $user->access_expired,
            'access_expiring_soon' => $user->access_expiring_soon,
            'directed_ponds' => $user->directedPonds ?? [],
            'directed_ponds_count' => $user->directed_ponds_count ?? ($user->directedPonds?->count() ?? 0),
            'directed_farms' => $user->directedFarms ?? [],
            'directed_farms_count' => $user->directed_farms_count ?? ($user->directedFarms?->count() ?? 0),
        ];
    }

    private function workerCredentialPayload(User $user): array
    {
        $director = $user->director;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'password' => $user->password_plain,
            'is_active' => $user->is_active,
            'director_id' => $user->director_id,
            'director' => $director ? [
                'id' => $director->id,
                'name' => $director->name,
                'phone' => $director->phone,
                'access_expires_at' => optional($director->access_expires_at)?->toIso8601String(),
                'access_days_left' => $director->access_days_left,
                'access_expired' => $director->access_expired,
                'is_active' => $director->is_active,
            ] : null,
            'assigned_farms' => $user->assignedFarms ?? [],
            'assigned_ponds' => $user->assignedPonds ?? [],
        ];
    }

    public function ponds(Request $request): JsonResponse
    {
        $ponds = Pond::with([
            'director:id,name,phone',
            'farm:id,name,region',
        ])
            ->withCount('workers')
            ->latest()
            ->get();

        return response()->json($ponds);
    }

    public function storePond(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'region' => ['required', 'string', Rule::in(Regions::all())],
            'district' => 'required|string|max:120',
            'area_m2' => 'required|numeric|min:0.01',
            'farm_id' => 'required|exists:farms,id',
            'notes' => 'nullable|string',
        ]);

        $farm = Farm::query()->findOrFail($data['farm_id']);

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

        ActivityLog::create([
            'pond_id' => $pond->id,
            'user_id' => $request->user()->id,
            'action' => 'pond_created',
            'title' => 'Havza qo‘shildi',
            'description' => $pond->name.' havzasi BOSS tomonidan yaratildi',
            'details' => [
                'region' => $pond->region,
                'district' => $pond->district,
                'area_m2' => $pond->area_m2,
            ],
            'performed_at' => now(),
        ]);

        return response()->json($pond->load('director:id,name,phone'), 201);
    }

    public function updatePond(Request $request, Pond $pond): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'region' => ['sometimes', 'string', Rule::in(Regions::all())],
            'district' => 'sometimes|nullable|string|max:120',
            'area_m2' => 'sometimes|numeric|min:0.01',
            'director_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_DIRECTOR)),
            ],
            'status' => 'sometimes|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $region = $data['region'] ?? $pond->region;
        $district = $data['district'] ?? $pond->district;
        if (! empty($district) && ! Districts::isValid($region, $district)) {
            throw ValidationException::withMessages([
                'district' => ['Tanlangan tuman/shahar ushbu viloyatga tegishli emas.'],
            ]);
        }

        $pond->update($data);

        return response()->json($pond->fresh()->load('director:id,name,phone'));
    }

    public function destroyPond(Pond $pond): JsonResponse
    {
        $pond->delete();

        return response()->json(['message' => 'Havza o\'chirildi']);
    }

    public function closureRequests(Request $request): JsonResponse
    {
        $status = $request->query('status', PondClosureRequest::STATUS_PENDING);

        $requests = PondClosureRequest::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with([
                'pond:id,name,region,district,area_m2,farm_id,director_id,current_stock',
                'pond.farm:id,name,region',
                'pond.director:id,name,phone',
                'requester:id,name,phone',
                'reviewer:id,name',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PondClosureRequest $item) => $this->formatClosureRequest($item));

        return response()->json([
            'requests' => $requests,
            'pending_count' => $this->pendingRequestsCount(),
        ]);
    }

    public function pondCreateRequests(Request $request): JsonResponse
    {
        $status = $request->query('status', PondCreateRequest::STATUS_PENDING);

        $requests = PondCreateRequest::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with([
                'farm:id,name,region,director_id',
                'farm.director:id,name,phone',
                'requester:id,name,phone',
                'reviewer:id,name',
                'pond:id,name',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PondCreateRequest $item) => $this->formatPondCreateRequest($item));

        return response()->json([
            'requests' => $requests,
            'pending_count' => $this->pendingRequestsCount(),
        ]);
    }

    public function approvePondCreateRequest(Request $request, PondCreateRequest $createRequest): JsonResponse
    {
        if (! $createRequest->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['Bu ariza allaqachon ko‘rib chiqilgan.'],
            ]);
        }

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $createRequest->load('farm');
        $farm = $createRequest->farm;

        if (! $farm) {
            throw ValidationException::withMessages([
                'farm' => ['Hojalik topilmadi.'],
            ]);
        }

        if (! Districts::isValid($createRequest->region, $createRequest->district)) {
            throw ValidationException::withMessages([
                'district' => ['Tanlangan tuman/shahar ushbu viloyatga tegishli emas.'],
            ]);
        }

        $pond = DB::transaction(function () use ($request, $createRequest, $farm, $data) {
            $pond = Pond::create([
                'farm_id' => $farm->id,
                'name' => $createRequest->name,
                'region' => $createRequest->region,
                'district' => $createRequest->district,
                'latitude' => null,
                'longitude' => null,
                'area_m2' => $createRequest->area_m2,
                'current_stock' => 0,
                'director_id' => $farm->director_id,
                'created_by' => $request->user()->id,
                'notes' => $createRequest->notes,
                'status' => 'active',
            ]);

            $createRequest->update([
                'status' => PondCreateRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                'pond_id' => $pond->id,
            ]);

            return $pond;
        });

        ActivityLog::create([
            'pond_id' => $pond->id,
            'user_id' => $request->user()->id,
            'action' => 'pond_create_approved',
            'title' => 'Havza ochish tasdiqlandi',
            'description' => $pond->name.' havzasi direktor arizasi bo‘yicha ochildi',
            'details' => [
                'request_id' => $createRequest->id,
                'region' => $pond->region,
                'district' => $pond->district,
                'area_m2' => $pond->area_m2,
            ],
            'performed_at' => now(),
        ]);

        $createRequest->refresh()->load([
            'farm:id,name,region,director_id',
            'farm.director:id,name,phone',
            'requester:id,name,phone',
            'reviewer:id,name',
            'pond:id,name',
        ]);

        return response()->json([
            'request' => $this->formatPondCreateRequest($createRequest),
            'pond' => $pond->load('director:id,name,phone'),
        ]);
    }

    public function rejectPondCreateRequest(Request $request, PondCreateRequest $createRequest): JsonResponse
    {
        if (! $createRequest->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['Bu ariza allaqachon ko‘rib chiqilgan.'],
            ]);
        }

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $createRequest->update([
            'status' => PondCreateRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        $createRequest->load([
            'farm:id,name,region,director_id',
            'farm.director:id,name,phone',
            'requester:id,name,phone',
            'reviewer:id,name',
            'pond:id,name',
        ]);

        return response()->json([
            'request' => $this->formatPondCreateRequest($createRequest),
        ]);
    }

    public function supportMessages(Request $request): JsonResponse
    {
        $filter = $request->query('status', 'unread');

        $messages = SupportMessage::query()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($filter === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->with('user:id,name,phone,role')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (SupportMessage $item) => $this->formatSupportMessage($item));

        $unreadCount = SupportMessage::query()->whereNull('read_at')->count();

        return response()->json([
            'messages' => $messages,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markSupportMessageRead(SupportMessage $supportMessage): JsonResponse
    {
        if ($supportMessage->read_at === null) {
            $supportMessage->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => $this->formatSupportMessage($supportMessage->fresh('user:id,name,phone,role')),
        ]);
    }

    private function pendingRequestsCount(): int
    {
        return PondClosureRequest::query()->where('status', PondClosureRequest::STATUS_PENDING)->count()
            + PondCreateRequest::query()->where('status', PondCreateRequest::STATUS_PENDING)->count();
    }

    private function formatPondCreateRequest(PondCreateRequest $item): array
    {
        return [
            'id' => $item->id,
            'type' => 'create_pond',
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
            'farm' => $item->farm ? [
                'id' => $item->farm->id,
                'name' => $item->farm->name,
                'region' => $item->farm->region,
                'director' => $item->farm->relationLoaded('director') && $item->farm->director
                    ? $item->farm->director->only(['id', 'name', 'phone'])
                    : null,
            ] : null,
            'pond' => $item->pond ? $item->pond->only(['id', 'name']) : null,
        ];
    }

    private function formatSupportMessage(SupportMessage $item): array
    {
        return [
            'id' => $item->id,
            'message' => $item->message,
            'user_role' => $item->user_role,
            'user_name' => $item->user_name,
            'user_phone' => $item->user_phone,
            'read_at' => optional($item->read_at)->toIso8601String(),
            'created_at' => optional($item->created_at)->toIso8601String(),
            'user' => $item->user ? $item->user->only(['id', 'name', 'phone', 'role']) : null,
        ];
    }

    public function approveClosureRequest(Request $request, PondClosureRequest $closureRequest): JsonResponse
    {
        if (! $closureRequest->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['Bu ariza allaqachon ko‘rib chiqilgan.'],
            ]);
        }

        $closureRequest->load('pond');
        $pond = $closureRequest->pond;

        if ($pond->isClosed()) {
            throw ValidationException::withMessages([
                'pond' => ['Bu havza allaqachon yopilgan.'],
            ]);
        }

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $summaryResponse = $this->directorCabinet->summary($request, $pond);
        $summarySnapshot = json_decode($summaryResponse->getContent(), true);

        $result = DB::transaction(function () use ($request, $closureRequest, $pond, $summarySnapshot, $data) {
            $closure = $this->pondClosure->executeApprovedClosure(
                $pond,
                $request->user(),
                $closureRequest->closed_on->toDateString(),
                $closureRequest->notes,
                $summarySnapshot,
                $closureRequest,
            );

            $closureRequest->update([
                'status' => PondClosureRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                'pond_closure_id' => $closure->id,
            ]);

            return $closure;
        });

        ActivityLog::create([
            'pond_id' => $pond->id,
            'user_id' => $request->user()->id,
            'action' => 'pond_closure_approved',
            'title' => 'Havza yopish tasdiqlandi',
            'description' => $pond->name.' havzasi BOSS tomonidan yopildi',
            'details' => [
                'request_id' => $closureRequest->id,
                'closure_id' => $result->id,
            ],
            'performed_at' => now(),
        ]);

        $closureRequest->refresh()->load([
            'requester:id,name',
            'reviewer:id,name',
            'pond:id,name,region',
            'closure',
        ]);

        return response()->json([
            'request' => $this->formatClosureRequest($closureRequest),
            'closure' => $result->load('user:id,name'),
            'pond' => $pond->fresh()->only(['id', 'name', 'closed_at', 'status', 'current_stock']),
        ]);
    }

    public function rejectClosureRequest(Request $request, PondClosureRequest $closureRequest): JsonResponse
    {
        if (! $closureRequest->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['Bu ariza allaqachon ko‘rib chiqilgan.'],
            ]);
        }

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $closureRequest->update([
            'status' => PondClosureRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        $closureRequest->load(['pond:id,name', 'requester:id,name', 'reviewer:id,name']);

        ActivityLog::create([
            'pond_id' => $closureRequest->pond_id,
            'user_id' => $request->user()->id,
            'action' => 'pond_closure_rejected',
            'title' => 'Havza yopish rad etildi',
            'description' => ($closureRequest->pond?->name ?? 'Havza').' yopish arizasi rad etildi',
            'details' => [
                'request_id' => $closureRequest->id,
                'review_notes' => $data['review_notes'] ?? null,
            ],
            'performed_at' => now(),
        ]);

        return response()->json([
            'request' => $this->formatClosureRequest($closureRequest),
        ]);
    }

    private function formatClosureRequest(PondClosureRequest $item): array
    {
        return [
            'id' => $item->id,
            'status' => $item->status,
            'closed_on' => $item->closed_on?->toDateString(),
            'notes' => $item->notes,
            'confirmations' => $item->confirmations,
            'analysis_snapshot' => $item->analysis_snapshot,
            'review_notes' => $item->review_notes,
            'reviewed_at' => optional($item->reviewed_at)->toIso8601String(),
            'created_at' => optional($item->created_at)->toIso8601String(),
            'requester' => $item->requester ? $item->requester->only(['id', 'name', 'phone']) : null,
            'reviewer' => $item->reviewer ? $item->reviewer->only(['id', 'name']) : null,
            'pond' => $item->pond ? [
                'id' => $item->pond->id,
                'name' => $item->pond->name,
                'region' => $item->pond->region,
                'area_m2' => $item->pond->area_m2 !== null ? (float) $item->pond->area_m2 : null,
                'current_stock' => (int) $item->pond->current_stock,
                'farm' => $item->pond->farm ? $item->pond->farm->only(['id', 'name', 'region']) : null,
                'director' => $item->pond->director ? $item->pond->director->only(['id', 'name', 'phone']) : null,
            ] : null,
            'closure' => $item->relationLoaded('closure') && $item->closure
                ? $item->closure->only(['id', 'closed_on', 'mode'])
                : null,
        ];
    }
}
