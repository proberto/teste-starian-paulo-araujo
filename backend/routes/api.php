<?php

use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/tarefas', [TaskController::class, 'index']);
Route::post('/tarefas', [TaskController::class, 'store']);
Route::delete('/tarefas/{id}', [TaskController::class, 'destroy']);
