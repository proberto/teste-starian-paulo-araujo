<?php

namespace App\Application\UseCases;

use App\Domain\Repositories\TaskRepositoryInterface;
use Illuminate\Support\Collection;

class ListTasksUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function execute(): Collection
    {
        return $this->taskRepository->all();
    }
}
