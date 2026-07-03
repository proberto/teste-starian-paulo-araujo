# Backend — Documentação da Refatoração

Este documento descreve **todas as modificações realizadas no backend Laravel** durante a refatoração do projeto, explicando **o que foi feito** e **por que** cada decisão foi tomada.

---

## Sumário

1. [Contexto e problema original](#contexto-e-problema-original)
2. [Arquitetura adotada](#arquitetura-adotada)
3. [Fluxo de uma requisição](#fluxo-de-uma-requisição)
4. [Modificações detalhadas](#modificações-detalhadas)
5. [Arquivos removidos](#arquivos-removidos)
6. [API REST](#api-rest)
7. [Como executar](#como-executar)
8. [Como testar](#como-testar)

---

## Contexto e problema original

O backend original era um projeto Laravel usado apenas como "casca", com a lógica de negócio implementada diretamente em `routes/api.php`. As principais más práticas eram:

| Problema | Impacto |
|----------|---------|
| Lógica em closures nas rotas | Impossível testar, reutilizar ou escalar o código |
| Persistência em `storage/tarefas.json` | Sem transações, sem concorrência segura, sem queries |
| Funções globais `lerTarefas()` / `salvarTarefas()` | Violação do SRP e acoplamento com o filesystem |
| Sem validação de entrada | Dados inválidos aceitos silenciosamente |
| Sem tratamento de erros no JSON | Risco de respostas corrompidas |
| Geração manual de IDs | Colisões em cenários concorrentes |
| DELETE retornava 204 mesmo se a tarefa não existisse | Contrato HTTP incorreto |
| API incluída via `require` em `web.php` | Mistura de responsabilidades entre rotas web e API |
| CORS com `Access-Control-Allow-Origin: *` | Inseguro para produção |
| Sem testes da API | Nenhuma garantia de regressão |

A refatoração transformou esse código em uma API REST estruturada, testável e preparada para evolução.

---

## Arquitetura adotada

Foi aplicada uma **Clean Architecture simplificada** (também conhecida como Hexagonal / Ports and Adapters), organizando o código em camadas com responsabilidades bem definidas:

```
HTTP (Controller, Request, Resource)
        ↓
Application (Use Cases, DTOs)
        ↓
Domain (Repository Interface)
        ↓
Infrastructure (Eloquent Repository)
        ↓
Database (PostgreSQL via Eloquent Model)
```

### Princípios aplicados

**SOLID**

- **S (Single Responsibility):** cada classe tem uma única razão para mudar — o Controller lida com HTTP, o Use Case com regra de negócio, o Repository com persistência.
- **O (Open/Closed):** novos repositórios (ex.: Redis, API externa) podem ser criados implementando `TaskRepositoryInterface` sem alterar os Use Cases.
- **L (Liskov Substitution):** qualquer implementação de `TaskRepositoryInterface` pode substituir `EloquentTaskRepository`.
- **I (Interface Segregation):** a interface do repositório expõe apenas os métodos necessários (`all`, `create`, `findById`, `delete`).
- **D (Dependency Inversion):** Use Cases dependem da abstração `TaskRepositoryInterface`, não do Eloquent diretamente. O binding é feito no `AppServiceProvider`.

**DRY (Don't Repeat Yourself)**

- Validação centralizada em `StoreTaskRequest` (não repetida em cada endpoint).
- Serialização JSON centralizada em `TaskResource` (formato único de resposta).
- Regras de negócio nos Use Cases (reutilizáveis em CLI, jobs ou testes).

**Spec-Driven Development**

- A API foi definida antes da implementação em `specs/openapi.yaml` na raiz do projeto.
- Os testes em `tests/Feature/TaskApiTest.php` validam o contrato definido na spec.

---

## Fluxo de uma requisição

Exemplo: `POST /api/v1/tarefas` com `{ "title": "Nova tarefa" }`

```
1. Route (api.php)
   → direciona para TaskController@store

2. StoreTaskRequest
   → valida: title obrigatório, string, 1–255 caracteres
   → retorna 422 automaticamente se inválido

3. TaskController@store
   → cria CreateTaskDTO com o título validado
   → chama CreateTaskUseCase

4. CreateTaskUseCase
   → delega ao TaskRepositoryInterface

5. EloquentTaskRepository
   → persiste via Model Task no PostgreSQL

6. TaskResource
   → serializa a resposta JSON sem wrapper "data"
   → retorna 201 Created
```

---

## Modificações detalhadas

### Rotas e bootstrap

#### `routes/api.php` — reescrito

**Antes:** closures com toda a lógica (ler/salvar JSON, gerar IDs, etc.).

**Depois:** rotas declarativas apontando para o `TaskController`:

```php
Route::get('/tarefas', [TaskController::class, 'index']);
Route::post('/tarefas', [TaskController::class, 'store']);
Route::delete('/tarefas/{id}', [TaskController::class, 'destroy']);
```

**Por quê:** separar definição de rotas da lógica de negócio. O Controller fica responsável por orquestrar a requisição, tornando o código testável e legível.

---

#### `routes/web.php` — simplificado

**Antes:** incluía `require __DIR__.'/api.php'`, misturando rotas web e API.

**Depois:** contém apenas a rota da página inicial (`/`).

**Por quê:** rotas web e API devem ser registradas separadamente. A API não pertence ao contexto web.

---

#### `bootstrap/app.php` — registro correto da API

**Antes:** apenas `web.php` registrado; API incluída indiretamente via `require` no web.

**Depois:**

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api/v1',
    ...
)
```

**Por quê:**
- Registro nativo do Laravel 11 para rotas de API.
- Prefixo `api/v1` permite versionamento futuro sem quebrar clientes existentes.
- Endpoints finais: `/api/v1/tarefas`.

---

### Camada HTTP

#### `app/Http/Controllers/TaskController.php` — criado

Responsável **apenas** por receber a requisição HTTP e devolver a resposta. Não contém lógica de negócio nem acesso direto ao banco.

| Método | Ação |
|--------|------|
| `index()` | Lista tarefas via `ListTasksUseCase` |
| `store()` | Cria tarefa via `CreateTaskUseCase` |
| `destroy()` | Remove tarefa via `DeleteTaskUseCase` |

**Por quê:** o Controller é a "porta de entrada" HTTP. Delegar para Use Cases mantém o código fino e testável.

---

#### `app/Http/Requests/StoreTaskRequest.php` — criado

Centraliza a validação do payload de criação:

```php
'title' => ['required', 'string', 'min:1', 'max:255']
```

**Por quê:**
- Antes, qualquer payload era aceito e títulos vazios viravam `"Tarefa sem título"`.
- Form Requests são o padrão Laravel para validação — retornam 422 automaticamente com mensagens claras.
- Evita duplicar regras de validação em múltiplos lugares (DRY).

---

#### `app/Http/Resources/TaskResource.php` — criado

Define o formato padrão de serialização JSON de uma tarefa:

```json
{
  "id": 1,
  "title": "Comprar leite",
  "completed": false,
  "created_at": "2026-07-01T12:00:00Z"
}
```

**Por quê:**
- Garante que todas as respostas da API tenham o mesmo formato.
- Oculta campos internos do Model (`updated_at`, etc.) que o frontend não precisa.
- `created_at` formatado em ISO 8601 para interoperabilidade.

No `AppServiceProvider`, foi adicionado `JsonResource::withoutWrapping()` para que a resposta **não** venha envolvida em `{ "data": ... }`, conforme definido na spec OpenAPI.

---

### Camada Application (Use Cases)

#### `app/Application/UseCases/ListTasksUseCase.php` — criado

Busca todas as tarefas via repositório.

**Por quê:** encapsula a intenção de negócio "listar tarefas". Se no futuro houver filtros, paginação ou cache, a mudança fica isolada aqui.

---

#### `app/Application/UseCases/CreateTaskUseCase.php` — criado

Recebe um `CreateTaskDTO` e cria a tarefa via repositório.

**Por quê:** separa a regra "criar tarefa" do HTTP. Pode ser reutilizado em seeders, comandos Artisan ou jobs.

---

#### `app/Application/UseCases/DeleteTaskUseCase.php` — criado

Remove uma tarefa por ID. Se não existir, lança `NotFoundHttpException` (404).

**Por quê:** antes o DELETE retornava 204 mesmo quando a tarefa não existia. Agora o contrato HTTP está correto — 204 para sucesso, 404 para recurso inexistente.

---

#### `app/Application/DTOs/CreateTaskDTO.php` — criado

Objeto imutável (`readonly`) que transporta dados validados entre camadas.

**Por quê:** evita passar arrays genéricos ou o `Request` inteiro para os Use Cases. O DTO documenta exatamente quais dados a operação precisa.

---

### Camada Domain

#### `app/Domain/Repositories/TaskRepositoryInterface.php` — criado

Contrato (porta) com os métodos de persistência:

- `all()` — listar todas
- `create(string $title)` — criar
- `findById(int $id)` — buscar por ID
- `delete(int $id)` — remover

**Por quê (Dependency Inversion):** os Use Cases dependem desta abstração, não do Eloquent. Isso permite trocar a implementação (banco, cache, mock em testes) sem alterar a lógica de negócio.

---

### Camada Infrastructure

#### `app/Infrastructure/Persistence/EloquentTaskRepository.php` — criado

Implementação concreta do repositório usando Eloquent ORM.

**Por quê:** isola o framework (Eloquent) da lógica de negócio. Se amanhã o projeto migrar para outro ORM ou banco, apenas esta classe muda.

Detalhes:
- `all()` ordena por `created_at` descendente (mais recentes primeiro).
- `delete()` retorna `false` se a tarefa não existir (o Use Case decide lançar 404).

---

### Model e banco de dados

#### `app/Models/Task.php` — criado

Model Eloquent com:
- `$fillable`: `title`, `completed`
- `$casts`: `completed` como `boolean`
- `HasFactory`: para testes e seeders

**Por quê:** substitui o armazenamento em JSON por um banco relacional com tipagem, timestamps automáticos e IDs auto-incrementais.

---

#### `database/migrations/2026_07_01_000000_create_tasks_table.php` — criado

Cria a tabela `tasks`:

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | bigint (PK) | Auto-incremento |
| `title` | string | Título da tarefa |
| `completed` | boolean | Status (default: false) |
| `created_at` / `updated_at` | timestamp | Auditoria |

**Por quê:**
- PostgreSQL oferece melhor escalabilidade, concorrência e suporte a tipos avançados em produção.
- Migrations versionam o schema e permitem reproduzir o banco em qualquer ambiente.
- IDs gerados pelo banco eliminam colisões da lógica manual `max(id) + 1`.

---

#### `database/seeders/DatabaseSeeder.php` — atualizado

Popula 3 tarefas iniciais **somente se a tabela estiver vazia**.

**Por quê:** garante dados de demonstração no primeiro `docker compose up`, sem duplicar tarefas a cada reinício do container.

---

#### `database/factories/TaskFactory.php` — criado

Factory para gerar tarefas fake nos testes.

**Por quê:** permite criar dados de teste de forma rápida e consistente com `Task::factory()->create()`.

---

### Configuração e providers

#### `app/Providers/AppServiceProvider.php` — atualizado

Dois registros importantes:

```php
// Dependency Injection: interface → implementação
$this->app->bind(TaskRepositoryInterface::class, EloquentTaskRepository::class);

// Resposta JSON sem wrapper "data"
JsonResource::withoutWrapping();
```

**Por quê:**
- O binding permite que o Laravel resolva automaticamente `TaskRepositoryInterface` como `EloquentTaskRepository` em qualquer injeção de dependência.
- `withoutWrapping()` alinha a resposta JSON com o contrato OpenAPI (array/objeto direto, sem `{ "data": ... }`).

---

#### `config/cors.php` — criado

Configura CORS restrito às origens do frontend Angular:

```php
'allowed_origins' => [
    'http://localhost:4200',
    'http://127.0.0.1:4200',
],
```

**Por quê:** substitui o middleware customizado `CorsMiddleware` que permitia `*` (qualquer origem). Em produção, origens devem ser explicitamente listadas por segurança.

---

### Testes

#### `tests/Feature/TaskApiTest.php` — criado

6 testes de integração cobrindo o contrato da API:

| Teste | Valida |
|-------|--------|
| `test_can_list_tasks` | GET retorna 200 com array de tarefas |
| `test_can_create_task` | POST retorna 201 e persiste no banco |
| `test_cannot_create_task_without_title` | POST sem title retorna 422 |
| `test_cannot_create_task_with_empty_title` | POST com title vazio retorna 422 |
| `test_can_delete_task` | DELETE retorna 204 e remove do banco |
| `test_cannot_delete_nonexistent_task` | DELETE com ID inexistente retorna 404 |

Usa `RefreshDatabase` para isolar cada teste com banco limpo (SQLite em memória via `phpunit.xml`, sem depender do PostgreSQL).

**Por quê:** garante que a refatoração não quebrou o comportamento esperado e serve como documentação viva do contrato da API (Spec-Driven).

---

### Docker

#### `Dockerfile` — reescrito

**Antes:** `php:8.3-fpm` com `CMD php-fpm` (incompatível com `php artisan serve` no compose).

**Depois:** `php:8.3-cli` com extensões `pdo_pgsql` e `zip`, `WORKDIR /var/www`, `CMD php artisan serve`.

**Por quê:**
- `php-cli` é o correto para rodar o servidor embutido do Artisan.
- `pdo_pgsql` habilita a conexão com PostgreSQL.
- `WORKDIR` alinhado com o volume do `docker-compose.yml` (`./backend:/var/www`).

O `docker-compose.yml` (na raiz) sobe o serviço `postgres` e executa migrations e seed automaticamente no startup do Laravel:

```bash
php artisan migrate --force &&
php artisan db:seed --force &&
php artisan serve --host=0.0.0.0 --port=8000
```

**Serviço PostgreSQL no Docker:**

| Variável | Valor |
|----------|-------|
| `POSTGRES_DB` | `tarefas` |
| `POSTGRES_USER` | `laravel` |
| `POSTGRES_PASSWORD` | `secret` |
| Porta exposta | `5432` |

---

## Arquivos removidos

| Arquivo | Motivo da remoção |
|---------|-------------------|
| `storage/tarefas.json` | Substituído por banco de dados PostgreSQL |
| `app/Http/Middleware/CorsMiddleware.php` | Substituído por `config/cors.php` nativo do Laravel |

---

## API REST

Base URL: `http://localhost:8000/api/v1`

| Método | Endpoint | Body | Sucesso | Erro |
|--------|----------|------|---------|------|
| `GET` | `/tarefas` | — | `200` — array de tarefas | — |
| `POST` | `/tarefas` | `{ "title": "..." }` | `201` — tarefa criada | `422` — validação |
| `DELETE` | `/tarefas/{id}` | — | `204` — sem conteúdo | `404` — não encontrada |

**Exemplo de resposta (GET /tarefas):**

```json
[
  {
    "id": 1,
    "title": "Tarefa 1",
    "completed": false,
    "created_at": "2026-07-01T12:00:00Z"
  }
]
```

A especificação completa está em `specs/openapi.yaml` na raiz do projeto.

---

## Como executar

### Via Docker (recomendado)

Na raiz do projeto:

```bash
docker compose up --build
```

API disponível em: http://localhost:8000/api/v1/tarefas

### Localmente (com PostgreSQL)

Certifique-se de ter o PostgreSQL rodando e crie o banco `tarefas`:

```bash
cd backend
cp .env.example .env
php artisan key:generate
composer install
php artisan migrate
php artisan db:seed
php artisan serve
```

Variáveis de conexão no `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tarefas
DB_USERNAME=laravel
DB_PASSWORD=secret
```

---

## Como testar

```bash
cd backend
php artisan test --filter=TaskApiTest
```

Resultado esperado: **6 testes passando, 28 assertions**.

Para rodar todos os testes:

```bash
php artisan test
```

---

## Estrutura final de pastas

```
backend/
├── app/
│   ├── Application/
│   │   ├── DTOs/
│   │   │   └── CreateTaskDTO.php
│   │   └── UseCases/
│   │       ├── ListTasksUseCase.php
│   │       ├── CreateTaskUseCase.php
│   │       └── DeleteTaskUseCase.php
│   ├── Domain/
│   │   └── Repositories/
│   │       └── TaskRepositoryInterface.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── TaskController.php
│   │   ├── Requests/
│   │   │   └── StoreTaskRequest.php
│   │   └── Resources/
│   │       └── TaskResource.php
│   ├── Infrastructure/
│   │   └── Persistence/
│   │       └── EloquentTaskRepository.php
│   └── Models/
│       └── Task.php
├── config/
│   └── cors.php
├── database/
│   ├── factories/
│   │   └── TaskFactory.php
│   ├── migrations/
│   │   └── 2026_07_01_000000_create_tasks_table.php
│   └── seeders/
│       └── DatabaseSeeder.php
├── routes/
│   ├── api.php
│   └── web.php
└── tests/
    └── Feature/
        └── TaskApiTest.php
```

---

## Próximos passos sugeridos

- [ ] Adicionar endpoint `PATCH /tarefas/{id}` para marcar como concluída
- [ ] Paginação na listagem (`GET /tarefas?page=1&per_page=10`)
- [ ] Laravel Pint para formatação automática de código
- [ ] CI/CD com GitHub Actions executando `php artisan test`
