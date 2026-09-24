<?php

namespace App\Observers;

use App\Models\Task;

class TaskObserver
{
    public function saving(Task $task): void
    {
        if ($task->isDirty('status')) {
            $task->completed_at = $task->status === 'completed' ? now() : null;
        }
    }

    public function created(Task $task): void
    {
        $task->project->recalculateStatus();
    }

    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            $task->project->recalculateStatus();
        }
    }

    public function deleted(Task $task): void
    {
        $task->project->recalculateStatus();
    }
}
