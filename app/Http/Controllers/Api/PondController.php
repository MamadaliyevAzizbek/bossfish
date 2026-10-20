<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PondController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $ponds = Pond::query()
            ->with(['owner:id,name', 'workers:id,name,phone,role'])
            ->withCount(['tasks', 'dailyReports', 'expenses'])
            ->when($user->isWorker(), function ($q) use ($user) {
                $q->whereHas('workers', fn ($w) => $w->where('users.id', $user->id));
            }, function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            })
            ->latest()
            ->get();

        return response()->json($ponds);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'area_m2' => 'nullable|numeric|min:0',
            'fish_type' => 'nullable|string|max:100',
            'capacity' => 'nullable|integer|min:0',
            'current_stock' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive,maintenance',
            'notes' => 'nullable|string',
        ]);

        $pond = $request->user()->ownedPonds()->create($data);
        $pond->load(['owner:id,name', 'workers:id,name,phone,role']);

        return response()->json($pond, 201);
    }

    public function show(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizePond($request, $pond);

        $pond->load([
            'owner:id,name,email,phone',
            'workers:id,name,email,phone,role',
            'tasks' => fn ($q) => $q->with('assignee:id,name')->latest()->limit(10),
            'dailyReports' => fn ($q) => $q->latest('report_date')->limit(7),
            'expenses' => fn ($q) => $q->latest('expense_date')->limit(10),
        ]);

        $pond->loadCount(['tasks', 'dailyReports', 'expenses', 'workerReports']);

        return response()->json($pond);
    }

    public function update(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizeOwner($request, $pond);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'area_m2' => 'nullable|numeric|min:0',
            'fish_type' => 'nullable|string|max:100',
            'capacity' => 'nullable|integer|min:0',
            'current_stock' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive,maintenance',
            'notes' => 'nullable|string',
        ]);

        $pond->update($data);

        return response()->json($pond->fresh(['owner:id,name', 'workers:id,name,phone,role']));
    }

    public function destroy(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizeOwner($request, $pond);
        $pond->delete();

        return response()->json(['message' => 'Havza o\'chirildi']);
    }

    public function attachWorker(Request $request, Pond $pond): JsonResponse
    {
        $this->authorizeOwner($request, $pond);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'position' => 'nullable|string|max:100',
            'hired_at' => 'nullable|date',
            'daily_wage' => 'nullable|numeric|min:0',
        ]);

        $worker = User::findOrFail($data['user_id']);

        $pond->workers()->syncWithoutDetaching([
            $worker->id => [
                'position' => $data['position'] ?? null,
                'hired_at' => $data['hired_at'] ?? null,
                'daily_wage' => $data['daily_wage'] ?? null,
            ],
        ]);

        return response()->json($pond->fresh(['workers:id,name,email,phone,role']));
    }

    public function detachWorker(Request $request, Pond $pond, User $user): JsonResponse
    {
        $this->authorizeOwner($request, $pond);
        $pond->workers()->detach($user->id);

        return response()->json(['message' => 'Ishchi havzadan olib tashlandi']);
    }

    private function authorizePond(Request $request, Pond $pond): void
    {
        $user = $request->user();

        if ($pond->owner_id === $user->id) {
            return;
        }

        if ($pond->workers()->where('users.id', $user->id)->exists()) {
            return;
        }

        abort(403, 'Bu havzaga ruxsat yo\'q');
    }

    private function authorizeOwner(Request $request, Pond $pond): void
    {
        if ($pond->owner_id !== $request->user()->id) {
            abort(403, 'Faqat egasi o\'zgartira oladi');
        }
    }
}
