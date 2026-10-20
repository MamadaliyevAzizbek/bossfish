<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientSubscription;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plan = $request->query('plan');
        $status = $request->query('status');
        $due = $request->boolean('due');
        $q = trim((string) $request->query('q', ''));

        $query = ClientSubscription::query()
            ->with([
                'director:id,name,phone,is_active,access_expires_at',
                'farm:id,name,region,director_id',
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN next_check_at IS NOT NULL AND next_check_at <= ? THEN 1 ELSE 2 END', [
                ClientSubscription::STATUS_ATTENTION,
                now()->toDateString(),
            ])
            ->orderBy('next_check_at')
            ->orderByDesc('id');

        if ($plan && in_array($plan, ClientSubscription::PLANS, true)) {
            $query->where('plan', $plan);
        }

        if ($status && in_array($status, ClientSubscription::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($due) {
            $query->whereIn('plan', [ClientSubscription::PLAN_CONTROL, ClientSubscription::PLAN_INDIVIDUAL])
                ->where(function ($inner) {
                    $inner->whereNull('next_check_at')
                        ->orWhereDate('next_check_at', '<=', now()->toDateString());
                });
        }

        if ($q !== '') {
            $query->whereHas('director', function ($director) use ($q) {
                $director->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $items = $query->get()->map(fn (ClientSubscription $sub) => $this->payload($sub));

        $all = ClientSubscription::query()->get();
        foreach ($all as $row) {
            $row->refreshExpiredStatus();
        }

        $summary = [
            'total' => $all->count(),
            'cabinet' => $all->where('plan', ClientSubscription::PLAN_CABINET)->count(),
            'control' => $all->where('plan', ClientSubscription::PLAN_CONTROL)->count(),
            'individual' => $all->where('plan', ClientSubscription::PLAN_INDIVIDUAL)->count(),
            'due_checks' => $all->filter(fn (ClientSubscription $s) => $s->needsCheck())->count(),
            'attention' => $all->where('status', ClientSubscription::STATUS_ATTENTION)->count(),
            'expiring' => $all->filter(fn (ClientSubscription $s) => $s->isExpiringSoon())->count(),
            'without_plan' => User::query()
                ->where('role', User::ROLE_DIRECTOR)
                ->whereDoesntHave('clientSubscription')
                ->count(),
        ];

        $directors = User::query()
            ->where('role', User::ROLE_DIRECTOR)
            ->with(['directedFarms:id,name,region,director_id', 'clientSubscription'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'phone' => $d->phone,
                'has_plan' => (bool) $d->clientSubscription,
                'farm' => $d->directedFarms->first()
                    ? [
                        'id' => $d->directedFarms->first()->id,
                        'name' => $d->directedFarms->first()->name,
                        'region' => $d->directedFarms->first()->region,
                    ]
                    : null,
            ]);

        return response()->json([
            'summary' => $summary,
            'subscriptions' => $items,
            'directors' => $directors,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $director = User::query()
            ->where('role', User::ROLE_DIRECTOR)
            ->findOrFail($data['director_id']);

        if (ClientSubscription::query()->where('director_id', $director->id)->exists()) {
            return response()->json(['message' => __('Bu direktorda allaqachon tarif bor.')], 422);
        }

        $farmId = $data['farm_id'] ?? optional($director->directedFarms()->first())->id;
        if ($farmId) {
            $farm = Farm::query()->find($farmId);
            if (! $farm || (int) $farm->director_id !== (int) $director->id) {
                return response()->json(['message' => __('Fermani topib bo‘lmadi.')], 422);
            }
        }

        $period = $data['billing_period'] ?? ClientSubscription::PERIOD_YEAR;
        $startsAt = $data['starts_at'];
        $endsAt = $data['ends_at'] ?? \Illuminate\Support\Carbon::parse($startsAt)
            ->addDays(ClientSubscription::periodDays($period))
            ->toDateString();

        $sub = ClientSubscription::create([
            'director_id' => $director->id,
            'farm_id' => $farmId,
            'plan' => $data['plan'],
            'billing_period' => $period,
            'price_amount' => $data['price_amount']
                ?? ClientSubscription::defaultPriceForPlan($data['plan'], $period),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'next_check_at' => $this->resolveNextCheck($data),
            'check_interval_days' => $data['check_interval_days']
                ?? ClientSubscription::defaultCheckInterval($data['plan']),
            'status' => $data['status'] ?? ClientSubscription::STATUS_ACTIVE,
            'attention_note' => $data['attention_note'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // Tarif muddati = kabinet kirish muddati
        $director->access_expires_at = $sub->ends_at->copy()->endOfDay();
        $director->save();

        return response()->json([
            'subscription' => $this->payload($sub->fresh(['director', 'farm'])),
        ], 201);
    }

    public function update(Request $request, ClientSubscription $subscription): JsonResponse
    {
        $data = $this->validated($request, updating: true);

        if (isset($data['farm_id'])) {
            if ($data['farm_id']) {
                $farm = Farm::query()->find($data['farm_id']);
                if (! $farm || (int) $farm->director_id !== (int) $subscription->director_id) {
                    return response()->json(['message' => __('Fermani topib bo‘lmadi.')], 422);
                }
            }
            $subscription->farm_id = $data['farm_id'];
        }

        foreach (['plan', 'billing_period', 'price_amount', 'starts_at', 'ends_at', 'next_check_at', 'check_interval_days', 'status', 'attention_note', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $subscription->{$field} = $data[$field];
            }
        }

        if (isset($data['plan']) && ! array_key_exists('check_interval_days', $data)) {
            $subscription->check_interval_days = ClientSubscription::defaultCheckInterval($data['plan']);
        }

        if (isset($data['plan']) && $data['plan'] === ClientSubscription::PLAN_CABINET) {
            $subscription->next_check_at = null;
            $subscription->check_interval_days = 0;
        }

        // Period o‘zgarsa va ends_at berilmasa — muddatni qayta hisobla
        if (isset($data['billing_period']) && ! array_key_exists('ends_at', $data) && $subscription->starts_at) {
            $subscription->ends_at = $subscription->starts_at->copy()
                ->addDays(ClientSubscription::periodDays($subscription->billing_period));
        }

        if (
            (isset($data['plan']) || isset($data['billing_period']))
            && ! array_key_exists('price_amount', $data)
        ) {
            $default = ClientSubscription::defaultPriceForPlan(
                $subscription->plan,
                $subscription->billing_period ?: ClientSubscription::PERIOD_YEAR
            );
            if ($default !== null) {
                $subscription->price_amount = $default;
            }
        }

        $subscription->save();

        if ($subscription->ends_at) {
            $director = $subscription->director;
            if ($director) {
                $director->access_expires_at = $subscription->ends_at->copy()->endOfDay();
                $director->save();
            }
        }

        return response()->json([
            'subscription' => $this->payload($subscription->fresh(['director', 'farm'])),
        ]);
    }

    public function markChecked(Request $request, ClientSubscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! in_array($subscription->plan, [ClientSubscription::PLAN_CONTROL, ClientSubscription::PLAN_INDIVIDUAL], true)) {
            return response()->json(['message' => __('Bu tarifda haftalik tekshiruv yo‘q.')], 422);
        }

        $subscription->markChecked($data['note'] ?? null);

        return response()->json([
            'subscription' => $this->payload($subscription->fresh(['director', 'farm'])),
        ]);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? ['sometimes'] : ['required'];

        return $request->validate([
            'director_id' => array_merge($required, [
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_DIRECTOR)),
            ]),
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
            'plan' => array_merge($required, [Rule::in(ClientSubscription::PLANS)]),
            'billing_period' => array_merge($updating ? ['sometimes'] : ['nullable'], [
                Rule::in(ClientSubscription::PERIODS),
            ]),
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'starts_at' => array_merge($required, ['date']),
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'next_check_at' => ['nullable', 'date'],
            'check_interval_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'status' => ['nullable', Rule::in(ClientSubscription::STATUSES)],
            'attention_note' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function resolveNextCheck(array $data): ?string
    {
        $plan = $data['plan'];
        if ($plan === ClientSubscription::PLAN_CABINET) {
            return null;
        }

        if (! empty($data['next_check_at'])) {
            return $data['next_check_at'];
        }

        return now()->addDays(ClientSubscription::defaultCheckInterval($plan))->toDateString();
    }

    private function payload(ClientSubscription $sub): array
    {
        $director = $sub->director;
        $farm = $sub->farm;

        return [
            'id' => $sub->id,
            'director_id' => $sub->director_id,
            'director_name' => $director?->name,
            'director_phone' => $director?->phone,
            'director_active' => (bool) ($director?->is_active),
            'access_days_left' => $director?->access_days_left,
            'access_expiring_soon' => (bool) ($director?->access_expiring_soon),
            'farm_id' => $sub->farm_id,
            'farm_name' => $farm?->name,
            'farm_region' => $farm?->region,
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
    }
}
