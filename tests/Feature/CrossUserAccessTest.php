<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Confirms a project/task owned by another user is unreachable, and that the
 * response is 404 in every case — not a mix of 403 (exists, not yours) and
 * 404 (doesn't exist) that would let an authenticated user enumerate other
 * users' resource ids by probing sequential ones.
 */
class CrossUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/projects/{$project->id}")->assertStatus(404);
    }

    public function test_user_cannot_update_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id, 'name' => 'original']);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'hijacked'])->assertStatus(404);
        $this->assertSame('original', $project->fresh()->name);
    }

    public function test_user_cannot_delete_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertStatus(404);
        $this->assertNotNull($project->fresh());
    }

    public function test_user_cannot_view_another_users_task(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/tasks/{$task->id}")->assertStatus(404);
    }

    public function test_user_cannot_update_or_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'name' => 'original']);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->putJson("/api/v1/tasks/{$task->id}", ['name' => 'hijacked'])->assertStatus(404);
        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertStatus(404);
        $this->assertSame('original', $task->fresh()->name);
    }

    public function test_user_cannot_list_or_create_tasks_under_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/projects/{$project->id}/tasks")->assertStatus(404);
        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['name' => 'x'])->assertStatus(404);
    }

    public function test_nonexistent_project_and_task_also_return_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/projects/999999')->assertStatus(404);
        $this->getJson('/api/v1/tasks/999999')->assertStatus(404);
    }
}
