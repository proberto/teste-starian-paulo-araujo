<?php

namespace App\Http\Controllers;

use App\Application\DTOs\CreateTaskDTO;
use App\Application\UseCases\CreateTaskUseCase;
use App\Application\UseCases\DeleteTaskUseCase;
use App\Application\UseCases\ListTasksUseCase;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(
        private ListTasksUseCase $listTasksUseCase,
        private CreateTaskUseCase $createTaskUseCase,
        private DeleteTaskUseCase $deleteTaskUseCase,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $tasks = $this->listTasksUseCase->execute();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $dto = new CreateTaskDTO(title: $request->validated('title'));
        $task = $this->createTaskUseCase->execute($dto);

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(int $id): Response
    {
        $this->deleteTaskUseCase->execute($id);

        return response()->noContent();
    }
}
