<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Função para ler o arquivo JSON (má prática - sem usar banco de dados)
function lerTarefas() {
    $arquivoJson = storage_path('tarefas.json');
    
    // Se o arquivo não existir, criar com dados iniciais
    if (!file_exists($arquivoJson)) {
        $tarefasIniciais = [
            ['id' => 1, 'title' => 'Tarefa 1', 'completed' => false],
            ['id' => 2, 'title' => 'Tarefa 2', 'completed' => true],
            ['id' => 3, 'title' => 'Tarefa 3', 'completed' => false],
        ];
        file_put_contents($arquivoJson, json_encode($tarefasIniciais));
        return $tarefasIniciais;
    }
    
    // Ler o arquivo JSON (sem tratamento de erro - má prática)
    $conteudo = file_get_contents($arquivoJson);
    return json_decode($conteudo, true);
}

// Função para salvar tarefas no arquivo JSON
function salvarTarefas($tarefas) {
    $arquivoJson = storage_path('tarefas.json');
    file_put_contents($arquivoJson, json_encode($tarefas));
}

// Listar todas as tarefas (sem usar controller - má prática)
Route::get('/tarefas', function () {
    $tarefas = lerTarefas();
    return response()->json($tarefas);
});

// Adicionar uma nova tarefa (sem validação - má prática)
Route::post('/tarefas', function (Request $request) {
    $tarefas = lerTarefas();
    
    $novaTarefa = [
        'id' => count($tarefas) > 0 ? max(array_column($tarefas, 'id')) + 1 : 1,
        'title' => $request->input('title', 'Tarefa sem título'),
        'completed' => false
    ];
    
    $tarefas[] = $novaTarefa;
    salvarTarefas($tarefas);
    
    return response()->json($novaTarefa, 201);
});

// Deletar uma tarefa (sem verificação de existência - má prática)
Route::delete('/tarefas/{id}', function ($id) {
    $tarefas = lerTarefas();
    
    // Encontrar o índice da tarefa com o ID especificado
    $index = array_search($id, array_column($tarefas, 'id'));
    
    // Remover a tarefa do array (sem verificar se existe - má prática)
    if ($index !== false) {
        array_splice($tarefas, $index, 1);
        salvarTarefas($tarefas);
    }
    
    // Retornar status 204 sem conteúdo (sem verificar se a tarefa existia - má prática)
    return response()->json(null, 204);
});
