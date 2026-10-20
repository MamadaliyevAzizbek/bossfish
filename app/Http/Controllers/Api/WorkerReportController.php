<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pond;
use App\Models\WorkerReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reports = WorkerReport::query()
            ->with(['pond:id,name', 'worker:id,name'])
            ->when($request->pond_id, fn ($q) => $q->where('pond_id', $request->pond_id))
            ->when($user->isWorker(), fn ($q) => $q->where('worker_id', $user->id))
            ->when($user->isOwner(), function ($q) use ($user) {
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
            'work_done' => 'required|string',
            'hours_worked' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'worker_id' => 'nullable|exists:users,id',
        ]);

        $pond = Pond::findOrFail($data['pond_id']);
        $user = $request->user();

        $workerId = $user->isOwner()
            ? ($data['worker_id'] ?? $user->id)
            : $user->id;

        if ($user->isWorker() && ! $pond->workers()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        if ($user->isOwner() && $pond->owner_id !== $user->id) {
            abort(403);
        }

        $report = WorkerReport::create([
            'pond_id' => $data['pond_id'],
            'worker_id' => $workerId,
            'report_date' => $data['report_date'],
            'work_done' => $data['work_done'],
            'hours_worked' => $data['hours_worked'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($report->load(['pond:id,name', 'worker:id,name']), 201);
    }

    public function show(Request $request, WorkerReport $workerReport): JsonResponse
    {
        $this->authorizeReport($request, $workerReport);

        return response()->json($workerReport->load(['pond:id,name', 'worker:id,name']));
    }

    public function update(Request $request, WorkerReport $workerReport): JsonResponse
    {
        $this->authorizeReport($request, $workerReport);

        $data = $request->validate([
            'work_done' => 'sometimes|string',
            'hours_worked' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $workerReport->update($data);

        return response()->json($workerReport->fresh(['pond:id,name', 'worker:id,name']));
    }

    public function destroy(Request $request, WorkerReport $workerReport): JsonResponse
    {
        $this->authorizeReport($request, $workerReport);
        $workerReport->delete();

        return response()->json(['message' => 'Hisobot o\'chirildi']);
    }

    private function authorizeReport(Request $request, WorkerReport $report): void
    {
        $user = $request->user();

        if ($report->pond->owner_id === $user->id || $report->worker_id === $user->id) {
            return;
        }

        abort(403);
    }
}
