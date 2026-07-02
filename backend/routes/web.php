<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Incluir rotas da API (má prática - misturando rotas web e api)
require __DIR__.'/api.php';
