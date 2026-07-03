<?php

namespace App\Domain\Repositories;

use App\Models\Task;
use Illuminate\Support\Collection;

interface TaskRepositoryInterface
{
    public function all(): Collection;

    public function create(string $title): Task;

    public function findById(int $id): ?Task;

    public function delete(int $id): bool;
}
