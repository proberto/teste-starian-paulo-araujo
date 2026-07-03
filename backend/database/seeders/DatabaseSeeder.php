<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Task::count() > 0) {
            return;
        }

        Task::insert([
            ['title' => 'Tarefa 1', 'completed' => false, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Tarefa 2', 'completed' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Tarefa 3', 'completed' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
