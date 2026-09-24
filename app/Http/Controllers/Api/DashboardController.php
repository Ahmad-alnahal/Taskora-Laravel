<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $tasksQuery = $user->tasks();

        $projectsCount = $user->projects()->count();
        $inProgressTasksCount = (clone $tasksQuery)->where('tasks.status', 'in_progress')->count();
        $completedTasksCount = (clone $tasksQuery)->where('tasks.status', 'completed')->count();

        // Each task earns at its own project's rate override, falling back to
        // the user's default rate — not one rate multiplied across every hour.
        $totalEarnings = (float) $user->tasks()
            ->selectRaw('SUM(tasks.actual_hours * COALESCE(projects.hourly_rate, ?)) as total', [$user->hourly_rate])
            ->value('total');

        return ApiResponse::success([
            'projects_count' => $projectsCount,
            'in_progress_tasks_count' => $inProgressTasksCount,
            'completed_tasks_count' => $completedTasksCount,
            'total_earnings' => round($totalEarnings, 2),
            'monthly_earnings' => $this->monthlyEarnings($user),
            'upcoming_tasks' => $this->upcomingTasks($user),
        ]);
    }

    private function monthlyEarnings($user): array
    {
        $months = collect(range(0, 5))
            ->map(fn ($i) => now()->subMonths($i)->format('Y-m'))
            ->reverse()
            ->values();

        // strftime() is SQLite-only — swap for DATE_FORMAT() if the DB connection changes.
        $earningsByMonth = $user->tasks()
            ->selectRaw(
                "strftime('%Y-%m', COALESCE(tasks.completed_at, tasks.updated_at)) as month, ".
                'SUM(tasks.actual_hours * COALESCE(projects.hourly_rate, ?)) as earnings',
                [$user->hourly_rate]
            )
            ->whereBetween(DB::raw('COALESCE(tasks.completed_at, tasks.updated_at)'), [now()->subMonths(5)->startOfMonth(), now()->endOfMonth()])
            ->groupBy('month')
            ->pluck('earnings', 'month');

        return $months->map(fn ($month) => [
            'month' => $month,
            'amount' => round((float) ($earningsByMonth[$month] ?? 0), 2),
        ])->all();
    }

    private function upcomingTasks($user): array
    {
        return $user->tasks()
            ->with('project:id,name')
            ->where('tasks.status', '!=', 'completed')
            ->whereNotNull('tasks.due_date')
            ->where('tasks.due_date', '>=', now()->toDateString())
            ->orderBy('tasks.due_date')
            ->limit(5)
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'name' => $task->name,
                'project_name' => $task->project->name,
                'due_date' => $task->due_date->toDateString(),
                'due_label' => $this->dueLabel($task->due_date),
            ])
            ->all();
    }

    private function dueLabel(Carbon $dueDate): string
    {
        $days = now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);

        return match (true) {
            $days === 0 => 'اليوم',
            $days === 1 => 'غداً',
            $days === 2 => 'بعد يومين',
            default => $dueDate->toDateString(),
        };
    }
}
