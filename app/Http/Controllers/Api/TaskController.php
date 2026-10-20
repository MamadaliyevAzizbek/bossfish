<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pond;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tasks = Task::query()
            ->with(['pond:id,name', 'assignee:id,name', 'creator:id,name'])
            ->when($request->pond_id, fn ($q, $id) => $q->where('pond_id', $request->pond_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($user->isWorker(), fn ($q) => $q->where('assigned_to', $user->id))
            ->when($user->isOwner(), function ($q) use ($user) {
                $q->whereHas('pond', fn ($p) => $p->where('owner_id', $user->id));
            })
            ->latest()
            ->paginate(20);

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pond_id' => 'required|exists:ponds,id',
            'assigned_to' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:pending,in_progress,done,cancelled',
        ]);

        $pond = Pond::findOrFail($data['pond_id']);
        if ($pond->owner_id !== $request->user()->id) {
            abort(403);
        }

        $task = Task::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($task->load(['pond:id,name', 'assignee:id,name']), 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);

        return response()->json($task->load(['pond:id,name', 'assignee:id,name', 'creator:id,name']));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);

        $data = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:pending,in_progress,done,cancelled',
            'completion_notes' => 'nullable|string',
        ]);

        if (($data['status'] ?? null) === 'done' && $task->status !== 'done') {
            $data['completed_at'] = now();
        }

        $task->update($data);

        return response()->json($task->fresh(['pond:id,name', 'assignee:id,name']));
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        if ($task->pond->owner_id !== $request->user()->id) {
            abort(403);
        }

        $task->delete();

        return response()->json(['message' => 'Vazifa o\'chirildi']);
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        $user = $request->user();

        if ($task->pond->owner_id === $user->id || $task->assigned_to === $user->id) {
            return;
        }

        abort(403);
    }
}
