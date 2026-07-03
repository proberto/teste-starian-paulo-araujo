<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateTaskDTO;
use App\Domain\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class CreateTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function execute(CreateTaskDTO $dto): Task
    {
        return $this->taskRepository->create($dto->title);
    }
}
