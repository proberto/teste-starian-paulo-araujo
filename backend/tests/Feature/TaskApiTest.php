<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_tasks(): void
    {
        Task::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/tarefas');

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['id', 'title', 'completed', 'created_at'],
            ]);
    }

    public function test_can_create_task(): void
    {
        $response = $this->postJson('/api/v1/tarefas', [
            'title' => 'Nova tarefa',
        ]);

        $response->assertCreated()
            ->assertJson([
                'title' => 'Nova tarefa',
                'completed' => false,
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Nova tarefa',
            'completed' => false,
        ]);
    }

    public function test_cannot_create_task_without_title(): void
    {
        $response = $this->postJson('/api/v1/tarefas', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_cannot_create_task_with_empty_title(): void
    {
        $response = $this->postJson('/api/v1/tarefas', [
            'title' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_can_delete_task(): void
    {
        $task = Task::factory()->create();

        $response = $this->deleteJson("/api/v1/tarefas/{$task->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_cannot_delete_nonexistent_task(): void
    {
        $response = $this->deleteJson('/api/v1/tarefas/999');

        $response->assertNotFound();
    }
}
