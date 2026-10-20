<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\Pond;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reports = DailyReport::query()
            ->with(['pond:id,name', 'user:id,name'])
            ->when($request->pond_id, fn ($q) => $q->where('pond_id', $request->pond_id))
            ->when($request->from, fn ($q) => $q->whereDate('report_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('report_date', '<=', $request->to))
            ->when($user->isWorker(), function ($q) use ($user) {
                $q->whereHas('pond.workers', fn ($w) => $w->where('users.id', $user->id));
            }, function ($q) use ($user) {
                $q->whereHas('pond', fn ($p) => $p->where('owner_id', $user->id));
            })
            ->latest('report_date')
            ->paginate(20);

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pond_id' => 'required|exists:ponds,id',
            'report_date' => 'required|date',
            'water_temp' => 'nullable|numeric',
            'ph_level' => 'nullable|numeric',
            'feeding_kg' => 'nullable|numeric|min:0',
            'mortality_count' => 'nullable|integer|min:0',
            'fish_condition' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $pond = Pond::findOrFail($data['pond_id']);
        $this->authorizeAccess($request, $pond);

        $report = DailyReport::updateOrCreate(
            [
                'pond_id' => $data['pond_id'],
                'report_date' => $data['report_date'],
            ],
            [
                ...$data,
                'user_id' => $request->user()->id,
            ]
        );

        return response()->json($report->load(['pond:id,name', 'user:id,name']), 201);
    }

    public function show(Request $request, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizeAccess($request, $dailyReport->pond);

        return response()->json($dailyReport->load(['pond:id,name', 'user:id,name']));
    }

    public function update(Request $request, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizeAccess($request, $dailyReport->pond);

        $data = $request->validate([
            'water_temp' => 'nullable|numeric',
            'ph_level' => 'nullable|numeric',
            'feeding_kg' => 'nullable|numeric|min:0',
            'mortality_count' => 'nullable|integer|min:0',
            'fish_condition' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $dailyReport->update($data);

        return response()->json($dailyReport->fresh(['pond:id,name', 'user:id,name']));
    }

    public function destroy(Request $request, DailyReport $dailyReport): JsonResponse
    {
        if ($dailyReport->pond->owner_id !== $request->user()->id) {
            abort(403);
        }

        $dailyReport->delete();

        return response()->json(['message' => 'Hisobot o\'chirildi']);
    }

    private function authorizeAccess(Request $request, Pond $pond): void
    {
        $user = $request->user();

        if ($pond->owner_id === $user->id) {
            return;
        }

        if ($pond->workers()->where('users.id', $user->id)->exists()) {
            return;
        }

        abort(403);
    }
}
