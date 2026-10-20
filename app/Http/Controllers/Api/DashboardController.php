<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Pond;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkerReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isWorker()) {
            $pondIds = $user->assignedPonds()->pluck('ponds.id');

            return response()->json([
                'role' => 'worker',
                'ponds_count' => $pondIds->count(),
                'my_tasks' => Task::where('assigned_to', $user->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->with('pond:id,name')
                    ->latest()
                    ->limit(10)
                    ->get(),
                'recent_reports' => WorkerReport::where('worker_id', $user->id)
                    ->with('pond:id,name')
                    ->latest('report_date')
                    ->limit(5)
                    ->get(),
            ]);
        }

        $pondIds = Pond::where('owner_id', $user->id)->pluck('id');

        return response()->json([
            'role' => $user->role,
            'ponds_count' => $pondIds->count(),
            'workers_count' => User::where('role', 'worker')
                ->whereHas('assignedPonds', fn ($q) => $q->whereIn('ponds.id', $pondIds))
                ->count(),
            'open_tasks' => Task::whereIn('pond_id', $pondIds)
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
            'month_expenses' => Expense::whereIn('pond_id', $pondIds)
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
            'today_reports' => DailyReport::whereIn('pond_id', $pondIds)
                ->whereDate('report_date', today())
                ->count(),
            'recent_tasks' => Task::whereIn('pond_id', $pondIds)
                ->with(['pond:id,name', 'assignee:id,name'])
                ->latest()
                ->limit(8)
                ->get(),
            'recent_expenses' => Expense::whereIn('pond_id', $pondIds)
                ->with('pond:id,name')
                ->latest('expense_date')
                ->limit(5)
                ->get(),
            'ponds' => Pond::where('owner_id', $user->id)
                ->withCount(['tasks', 'workers'])
                ->get(['id', 'name', 'location', 'status', 'fish_type', 'current_stock']),
        ]);
    }
}
