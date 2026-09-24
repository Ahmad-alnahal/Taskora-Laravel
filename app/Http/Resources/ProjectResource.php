<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tasksCount = (int) ($this->tasks_count ?? 0);
        $completedTasksCount = (int) ($this->completed_tasks_count ?? 0);
        $estimatedHoursSum = (float) ($this->estimated_hours_sum ?? 0);
        $actualHoursSum = (float) ($this->actual_hours_sum ?? 0);

        // نسبة الساعات (actual/estimated)، وليس عدد المهام المكتملة/الكلي —
        // مطابق لـ Project.progressPercent في Flutter (قرار مستخدم 2026-09-24،
        // راجع ai-session-memory.md §26 وdatabase-schema.md §4). بلا ساعات
        // متوقعة مسجَّلة إطلاقاً: 0% إن لم تُسجَّل ساعات فعلية أيضاً، وإلا
        // 100% (إنجاز بلا أساس مقارنة). مقصوصة عند 100% دائماً للعرض.
        $progressRatio = $estimatedHoursSum > 0
            ? $actualHoursSum / $estimatedHoursSum
            : ($actualHoursSum > 0 ? 1 : 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_name' => $this->client_name,
            'status' => $this->status,
            'deadline' => $this->deadline?->toDateString(),
            'hourly_rate' => $this->hourly_rate !== null ? (float) $this->hourly_rate : null,
            'tasks_count' => $tasksCount,
            'completed_tasks_count' => $completedTasksCount,
            'estimated_hours_sum' => $estimatedHoursSum,
            'actual_hours_sum' => $actualHoursSum,
            'progress_percent' => (int) round(min(max($progressRatio, 0), 1) * 100),
            'description' => $this->when($request->routeIs('projects.show'), $this->description),
            'created_at' => $this->when($request->routeIs('projects.show'), fn () => $this->created_at->toIso8601String()),
        ];
    }
}
