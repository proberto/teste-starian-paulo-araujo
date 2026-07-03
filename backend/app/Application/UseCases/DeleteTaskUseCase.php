<?php

namespace App\Application\UseCases;

use App\Domain\Repositories\TaskRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function execute(int $id): void
    {
        $deleted = $this->taskRepository->delete($id);

        if (! $deleted) {
            throw new NotFoundHttpException('Tarefa não encontrada.');
        }
    }
}
