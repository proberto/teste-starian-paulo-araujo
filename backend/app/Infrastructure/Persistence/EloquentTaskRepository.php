<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Illuminate\Support\Collection;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    public function all(): Collection
    {
        return Task::orderBy('created_at', 'desc')->get();
    }

    public function create(string $title): Task
    {
        return Task::create([
            'title' => $title,
            'completed' => false,
        ]);
    }

    public function findById(int $id): ?Task
    {
        return Task::find($id);
    }

    public function delete(int $id): bool
    {
        $task = $this->findById($id);

        if (! $task) {
            return false;
        }

        return (bool) $task->delete();
    }
}
