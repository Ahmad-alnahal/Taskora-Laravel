<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\CreatesIdempotently;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use CreatesIdempotently;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'completed', 'archived'])],
        ]);

        $projects = $request->user()->projects()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
            ])
            ->withSum('tasks as estimated_hours_sum', 'estimated_hours')
            ->withSum('tasks as actual_hours_sum', 'actual_hours')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success([
            'items' => ProjectResource::collection($projects->items()),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $this->authorize('create', Project::class);

        $data = $request->validated();

        [$project, $created] = $this->createIdempotently(
            $request->user()->projects(),
            $data + ['status' => Project::STATUS_ACTIVE],
            $data['client_uuid'] ?? null,
        );

        return ApiResponse::success(new ProjectResource($project), 'تم إنشاء المشروع', $created ? 201 : 200);
    }

    public function show(Request $request, int $project)
    {
        $project = $request->user()->projects()->findOrFail($project);

        $this->authorize('view', $project);

        $project->loadCount([
            'tasks',
            'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
        ])->loadSum('tasks as estimated_hours_sum', 'estimated_hours')
            ->loadSum('tasks as actual_hours_sum', 'actual_hours');

        return ApiResponse::success(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, int $project)
    {
        $project = $request->user()->projects()->findOrFail($project);

        $this->authorize('update', $project);

        $project->update($request->validated());

        return ApiResponse::success(new ProjectResource($project), 'تم تحديث المشروع');
    }

    public function destroy(Request $request, int $project)
    {
        $project = $request->user()->projects()->findOrFail($project);

        $this->authorize('delete', $project);

        if ($project->tasks()->where('status', '!=', 'completed')->exists()) {
            return ApiResponse::error('لا يمكن حذف المشروع لوجود مهام غير مكتملة فيه', 409);
        }

        $project->delete();

        return ApiResponse::success(message: 'تم حذف المشروع');
    }
}
