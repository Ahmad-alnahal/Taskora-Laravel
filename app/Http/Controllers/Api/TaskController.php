<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\CreatesIdempotently;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ExportTasksRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TaskController extends Controller
{
    use CreatesIdempotently;

    public function index(Request $request, int $project)
    {
        $project = $request->user()->projects()->findOrFail($project);

        $this->authorize('view', $project);

        $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'in_review', 'completed'])],
        ]);

        $tasks = $project->tasks()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->get();

        return ApiResponse::success(['items' => TaskResource::collection($tasks)]);
    }

    public function store(StoreTaskRequest $request, int $project)
    {
        $project = $request->user()->projects()->findOrFail($project);

        $this->authorize('create', [Task::class, $project]);

        $data = $request->validated();

        [$task, $created] = $this->createIdempotently(
            $project->tasks(),
            $data + ['status' => 'pending', 'priority' => 'medium', 'estimated_hours' => 0],
            $data['client_uuid'] ?? null,
        );

        return ApiResponse::success(new TaskResource($task), 'تم إضافة المهمة', $created ? 201 : 200);
    }

    public function show(Request $request, int $task)
    {
        $task = $request->user()->tasks()->findOrFail($task);

        $this->authorize('view', $task);

        $task->load('project:id,name');

        return ApiResponse::success(new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, int $task)
    {
        $task = $request->user()->tasks()->findOrFail($task);

        $this->authorize('update', $task);

        $task->update($request->validated());

        return ApiResponse::success(new TaskResource($task), 'تم تحديث المهمة');
    }

    public function destroy(Request $request, int $task)
    {
        $task = $request->user()->tasks()->findOrFail($task);

        $this->authorize('delete', $task);

        $task->delete();

        return ApiResponse::success(message: 'تم حذف المهمة');
    }

    public function export(ExportTasksRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        if (isset($data['project_id'])) {
            $project = $user->projects()->findOrFail($data['project_id']);
            $tasks = $project->tasks();
        } else {
            $tasks = $user->tasks();
        }

        $tasks = $tasks->when(isset($data['from']), fn ($q) => $q->whereDate('due_date', '>=', $data['from']))
            ->when(isset($data['to']), fn ($q) => $q->whereDate('due_date', '<=', $data['to']))
            ->with('project:id,name')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['المشروع', 'المهمة', 'الحالة', 'الأولوية', 'الساعات المتوقعة', 'الساعات الفعلية', 'تاريخ الاستحقاق'], null, 'A1');

        foreach ($tasks as $index => $task) {
            $sheet->fromArray([
                $this->sanitizeCell($task->project->name),
                $this->sanitizeCell($task->name),
                $task->status,
                $task->priority,
                (float) $task->estimated_hours,
                (float) $task->actual_hours,
                $task->due_date?->toDateString(),
            ], null, 'A'.($index + 2));
        }

        $fileName = 'exports/tasks_'.now()->format('Y-m-d_His').'_'.$user->id.'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($tempPath);

        Storage::disk('local')->put($fileName, file_get_contents($tempPath));
        @unlink($tempPath);

        $expiresAt = now()->addHour();
        $downloadUrl = URL::temporarySignedRoute('tasks.export.download', $expiresAt, ['path' => $fileName]);

        return ApiResponse::success([
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ], 'الملف جاهز');
    }

    /**
     * Neutralize free-text cell values that Excel would otherwise interpret
     * as a formula (leading =, +, -, @) — classic CSV/Formula Injection.
     */
    private function sanitizeCell(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    public function downloadExport(Request $request)
    {
        $path = $request->query('path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return ApiResponse::error('الملف غير موجود أو انتهت صلاحيته', 404);
        }

        return Storage::disk('local')->download($path);
    }
}
