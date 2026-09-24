<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_with_a_new_client_uuid_returns_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $clientUuid = (string) Str::uuid();

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'مشروع تجريبي',
            'client_uuid' => $clientUuid,
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, Project::count());
    }

    public function test_retrying_the_same_client_uuid_returns_the_same_project_without_duplicating_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $clientUuid = (string) Str::uuid();
        $payload = ['name' => 'مشروع تجريبي', 'client_uuid' => $clientUuid];

        $first = $this->postJson('/api/v1/projects', $payload);
        $first->assertStatus(201);
        $firstId = $first->json('data.id');

        $retry = $this->postJson('/api/v1/projects', $payload);
        $retry->assertStatus(200);
        $retryId = $retry->json('data.id');

        $this->assertSame($firstId, $retryId);
        $this->assertSame(1, Project::count());
    }

    public function test_requests_without_a_client_uuid_still_create_separate_projects(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = ['name' => 'مشروع بدون uuid'];

        $this->postJson('/api/v1/projects', $payload)->assertStatus(201);
        $this->postJson('/api/v1/projects', $payload)->assertStatus(201);

        $this->assertSame(2, Project::count());
    }

    public function test_retrying_the_same_client_uuid_for_a_task_returns_the_same_task_without_duplicating_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = Project::factory()->create(['user_id' => $user->id]);

        $clientUuid = (string) Str::uuid();
        $payload = ['name' => 'مهمة تجريبية', 'client_uuid' => $clientUuid];

        $first = $this->postJson("/api/v1/projects/{$project->id}/tasks", $payload);
        $first->assertStatus(201);

        $retry = $this->postJson("/api/v1/projects/{$project->id}/tasks", $payload);
        $retry->assertStatus(200);

        $this->assertSame($first->json('data.id'), $retry->json('data.id'));
        $this->assertSame(1, Task::where('project_id', $project->id)->count());
    }
}
