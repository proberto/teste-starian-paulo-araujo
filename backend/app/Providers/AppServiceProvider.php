<?php

namespace App\Providers;

use App\Domain\Repositories\TaskRepositoryInterface;
use App\Infrastructure\Persistence\EloquentTaskRepository;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaskRepositoryInterface::class, EloquentTaskRepository::class);
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
