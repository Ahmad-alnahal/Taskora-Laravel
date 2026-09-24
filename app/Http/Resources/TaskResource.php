<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->when($request->routeIs('tasks.show'), $this->project_id),
            'project_name' => $this->when($request->routeIs('tasks.show'), fn () => $this->project->name),
            'name' => $this->name,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'estimated_hours' => (float) $this->estimated_hours,
            'actual_hours' => (float) $this->actual_hours,
            'due_date' => $this->due_date?->toDateString(),
        ];
    }
}
