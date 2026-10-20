<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Pond;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $expenses = Expense::query()
            ->with(['pond:id,name', 'user:id,name'])
            ->when($request->pond_id, fn ($q) => $q->where('pond_id', $request->pond_id))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->from, fn ($q) => $q->whereDate('expense_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('expense_date', '<=', $request->to))
            ->whereHas('pond', fn ($p) => $p->where('owner_id', $user->id))
            ->latest('expense_date')
            ->paginate(20);

        return response()->json($expenses);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $byCategory = Expense::query()
            ->select('category', DB::raw('SUM(amount) as total'))
            ->whereHas('pond', fn ($p) => $p->where('owner_id', $user->id))
            ->when($request->pond_id, fn ($q) => $q->where('pond_id', $request->pond_id))
            ->when($request->from, fn ($q) => $q->whereDate('expense_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('expense_date', '<=', $request->to))
            ->groupBy('category')
            ->get();

        $byPond = Expense::query()
            ->select('pond_id', DB::raw('SUM(amount) as total'))
            ->with('pond:id,name')
            ->whereHas('pond', fn ($p) => $p->where('owner_id', $user->id))
            ->when($request->from, fn ($q) => $q->whereDate('expense_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('expense_date', '<=', $request->to))
            ->groupBy('pond_id')
            ->get();

        $grandTotal = $byCategory->sum('total');

        return response()->json([
            'grand_total' => $grandTotal,
            'by_category' => $byCategory,
            'by_pond' => $byPond,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pond_id' => 'required|exists:ponds,id',
            'category' => 'required|in:feed,medicine,salary,equipment,transport,other',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $pond = Pond::findOrFail($data['pond_id']);
        if ($pond->owner_id !== $request->user()->id) {
            abort(403);
        }

        $expense = Expense::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($expense->load(['pond:id,name']), 201);
    }

    public function show(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->pond->owner_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json($expense->load(['pond:id,name', 'user:id,name']));
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->pond->owner_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'category' => 'sometimes|in:feed,medicine,salary,equipment,transport,other',
            'amount' => 'sometimes|numeric|min:0',
            'expense_date' => 'sometimes|date',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $expense->update($data);

        return response()->json($expense->fresh(['pond:id,name']));
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->pond->owner_id !== $request->user()->id) {
            abort(403);
        }

        $expense->delete();

        return response()->json(['message' => 'Xarajat o\'chirildi']);
    }
}
