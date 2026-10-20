<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $workers = User::query()
            ->where('role', 'worker')
            ->whereHas('assignedPonds', fn ($q) => $q->where('owner_id', $user->id))
            ->with(['assignedPonds' => fn ($q) => $q->where('owner_id', $user->id)->select('ponds.id', 'ponds.name')])
            ->orderBy('name')
            ->get();

        return response()->json($workers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:50',
            'pond_ids' => 'nullable|array',
            'pond_ids.*' => 'exists:ponds,id',
            'position' => 'nullable|string|max:100',
            'daily_wage' => 'nullable|numeric|min:0',
        ]);

        $owner = $request->user();

        $worker = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => 'worker',
        ]);

        if (! empty($data['pond_ids'])) {
            $ownedPondIds = $owner->ownedPonds()->whereIn('id', $data['pond_ids'])->pluck('id');
            $sync = [];
            foreach ($ownedPondIds as $pondId) {
                $sync[$pondId] = [
                    'position' => $data['position'] ?? null,
                    'hired_at' => now()->toDateString(),
                    'daily_wage' => $data['daily_wage'] ?? null,
                ];
            }
            $worker->assignedPonds()->sync($sync);
        }

        return response()->json($worker->load('assignedPonds:id,name'), 201);
    }

    public function update(Request $request, User $worker): JsonResponse
    {
        if ($worker->role !== 'worker') {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($worker->id)],
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'pond_ids' => 'nullable|array',
            'pond_ids.*' => 'exists:ponds,id',
            'position' => 'nullable|string|max:100',
            'daily_wage' => 'nullable|numeric|min:0',
        ]);

        $owner = $request->user();

        $update = collect($data)->only(['name', 'email', 'phone', 'is_active'])->all();
        if (! empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        $worker->update($update);

        if (array_key_exists('pond_ids', $data)) {
            $ownedPondIds = $owner->ownedPonds()->whereIn('id', $data['pond_ids'] ?? [])->pluck('id');
            $sync = [];
            foreach ($ownedPondIds as $pondId) {
                $sync[$pondId] = [
                    'position' => $data['position'] ?? null,
                    'daily_wage' => $data['daily_wage'] ?? null,
                ];
            }
            $worker->assignedPonds()->sync($sync);
        }

        return response()->json($worker->fresh()->load('assignedPonds:id,name'));
    }

    public function destroy(Request $request, User $worker): JsonResponse
    {
        if ($worker->role !== 'worker') {
            abort(404);
        }

        $ownerPondIds = $request->user()->ownedPonds()->pluck('id');
        $worker->assignedPonds()->detach($ownerPondIds);
        $worker->update(['is_active' => false]);

        return response()->json(['message' => 'Ishchi faolsizlantirildi']);
    }
}
