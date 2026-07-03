# Plano de Refatoração Fullstack — Angular + Laravel

> Documento gerado a partir da análise do projeto de teste técnico Starian.
> Objetivo: refatorar o código existente aplicando boas práticas, SOLID, DRY, Spec-Driven Development e arquitetura escalável.

---

## 1. Diagnóstico — Más práticas identificadas

### 1.1 Backend (Laravel / PHP)

| Área | Problema |
|------|----------|
| Arquitetura | Toda a lógica em closures dentro de `api.php`, sem Controllers, Services ou Repositories |
| Persistência | Dados em arquivo JSON (`storage/tarefas.json`) em vez de banco de dados |
| SRP (SOLID) | Funções globais `lerTarefas()` e `salvarTarefas()` misturam I/O, serialização e regra de negócio |
| Validação | POST aceita qualquer payload; título padrão `"Tarefa sem título"` sem validação |
| Tratamento de erros | `json_decode` sem checagem; `file_get_contents` sem try/catch |
| Rotas | API incluída via `require` em `web.php` em vez de `routes/api.php` registrado no bootstrap |
| IDs | Geração de ID frágil (`max(array_column(...)) + 1`) — colisões em concorrência |
| DELETE | Retorna 204 mesmo quando a tarefa não existe; comparação de ID pode falhar (string vs int) |
| CORS | `Access-Control-Allow-Origin: *` sem restrição |
| Testes | Apenas teste de exemplo; nenhum teste da API de tarefas |
| Docker | `Dockerfile` usa `php-fpm`, mas `docker-compose` roda `php artisan serve`; paths inconsistentes |

### 1.2 Frontend (Angular)

| Área | Problema |
|------|----------|
| God Component | `AppComponent` concentra UI, estado, HTTP e tratamento de erro |
| SRP / DIP | Componente depende diretamente de `HttpClient`, sem camada de serviço |
| Tipagem | Uso de `any[]` e `any` — sem interfaces/modelos |
| HttpClient | `HttpClientModule` no componente em vez de `provideHttpClient()` em `app.config.ts` |
| Configuração | URL da API hardcoded (`http://localhost:8000/tarefas`) |
| Tratamento de erro | Em falha, cria dados fake/offline — mascara erros reais |
| RxJS | Uso de `.subscribe()` com callbacks em vez de `async` pipe ou operadores |
| Estilos | Estilos inline no HTML; referência a `app.component.scss` inexistente |
| Responsividade | Largura fixa (`300px`), sem breakpoints |
| Rotas | `app.routes.ts` vazio; `<router-outlet />` sem uso |
| Estrutura | Sem features, shared, core, componentes reutilizáveis |
| Testes | Nenhum teste de componente ou serviço |

### 1.3 Infraestrutura

- `docker-compose` sem serviço de banco de dados
- Paths inconsistentes entre Dockerfiles e compose
- Sem variáveis de ambiente para URL da API no frontend
- Sem CI/CD

---

## 2. Princípios orientadores

### 2.1 SOLID

| Princípio | Backend | Frontend |
|-----------|---------|----------|
| **S** — Single Responsibility | Controller só HTTP; Use Case só regra; Repository só persistência | Componentes de apresentação vs smart components |
| **O** — Open/Closed | Novos repositórios sem alterar Use Cases | Novos features sem alterar core |
| **L** — Liskov Substitution | Implementações de `TaskRepositoryInterface` intercambiáveis | — |
| **I** — Interface Segregation | Interfaces pequenas e focadas | — |
| **D** — Dependency Inversion | Use Cases dependem de abstrações, não de Eloquent | Componentes injetam `TaskService`, não `HttpClient` |

### 2.2 DRY (Don't Repeat Yourself)

- Validação centralizada em Form Requests (backend)
- Serialização em API Resources (backend)
- `TaskService` como única fonte de chamadas HTTP (frontend)
- Modelos/interfaces tipados compartilhados por feature
- Estilos em SCSS com variáveis e mixins reutilizáveis

### 2.3 Spec-Driven Development

1. Escrever especificação antes de implementar (OpenAPI + critérios de aceite)
2. Definir contratos de teste a partir da spec
3. Implementar backend e frontend contra o contrato
4. Validar com testes automatizados

---

## 3. Arquitetura alvo

### 3.1 Visão geral: Modular Monolith + API REST versionada + Frontend Feature-Sliced

```
┌─────────────────────────────────────────────────────────┐
│  Angular (Feature-Sliced)                               │
│  ┌─────────────┐    ┌──────────────┐    ┌─────────────┐  │
│  │ Presentation│───▶│ Task Service │───▶│ HTTP Client │  │
│  │ Components  │    └──────────────┘    └──────┬──────┘  │
└────────────────────────────────────────────────│─────────┘
                                                  │ REST /api/v1
┌────────────────────────────────────────────────│─────────┐
│  Laravel (Clean / Hexagonal)                    ▼         │
│  ┌────────────┐   ┌───────────┐   ┌──────────────────┐ │
│  │ Controller │──▶│ Use Cases │──▶│ Repository (intf) │ │
│  └────────────┘   └───────────┘   └────────┬─────────┘ │
│                                             ▼            │
│                                    ┌─────────────────┐  │
│                                    │ Eloquent / DB   │  │
│                                    └─────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### 3.2 Estrutura de pastas — Backend

```
backend/
├── app/
│   ├── Domain/
│   │   ├── Entities/Task.php
│   │   └── Repositories/TaskRepositoryInterface.php
│   ├── Application/
│   │   ├── DTOs/CreateTaskDTO.php
│   │   └── UseCases/
│   │       ├── ListTasksUseCase.php
│   │       ├── CreateTaskUseCase.php
│   │       └── DeleteTaskUseCase.php
│   ├── Infrastructure/
│   │   └── Persistence/EloquentTaskRepository.php
│   └── Http/
│       ├── Controllers/TaskController.php
│       ├── Requests/StoreTaskRequest.php
│       └── Resources/TaskResource.php
├── database/migrations/
└── routes/api.php
```

### 3.3 Estrutura de pastas — Frontend

```
frontend/src/app/
├── core/
│   ├── services/api-config.service.ts
│   └── interceptors/error.interceptor.ts
├── shared/
│   ├── models/task.model.ts
│   └── ui/
├── features/
│   └── tasks/
│       ├── components/
│       │   ├── task-list/
│       │   ├── task-item/
│       │   └── task-form/
│       ├── services/task.service.ts
│       ├── tasks.routes.ts
│       └── tasks.component.ts
└── app.routes.ts
```

### 3.4 Evolução futura

| Horizonte | Ação |
|-----------|------|
| Curto prazo | Modular Monolith — suficiente para a maioria dos casos |
| Médio prazo | Redis para cache; filas (Laravel Queue) para tarefas assíncronas |
| Longo prazo | Extrair domínios em microserviços somente se houver necessidade real |

---

## 4. Fases de implementação

### Fase 0 — Spec-Driven Development

- [x] Criar `specs/openapi.yaml` com endpoints e modelos
- [x] Criar `specs/acceptance-criteria.md` com critérios de aceite
- [x] Definir contrato de testes a partir da spec

**Contrato da API:**

| Método | Endpoint | Descrição | Status |
|--------|----------|-----------|--------|
| GET | `/api/v1/tarefas` | Listar tarefas | 200 |
| POST | `/api/v1/tarefas` | Criar tarefa | 201 / 422 |
| DELETE | `/api/v1/tarefas/{id}` | Remover tarefa | 204 / 404 |

**Modelo Task:**
```json
{
  "id": 1,
  "title": "string (1-255 chars, obrigatório)",
  "completed": false,
  "created_at": "ISO 8601"
}
```

### Fase 1 — Fundação e infraestrutura

- [x] Corrigir Docker (paths, serviço de DB, healthchecks)
- [x] Criar migration `tasks` e Model `Task`
- [x] Registrar `api.php` no `bootstrap/app.php` com prefixo `/api/v1`
- [x] Configurar CORS via `config/cors.php` com origens por ambiente
- [x] Remover persistência em JSON

### Fase 2 — Backend (Clean Architecture)

- [x] `TaskRepositoryInterface` + `EloquentTaskRepository`
- [x] Use Cases: List, Create, Delete
- [x] `TaskController` + `StoreTaskRequest` + `TaskResource`
- [x] Registrar bindings no `AppServiceProvider`
- [x] Testes de feature PHPUnit

### Fase 3 — Frontend (Feature-Sliced)

- [x] Model `Task` tipado
- [x] `TaskService` com `provideHttpClient()`
- [x] Componentes: `task-form`, `task-list`, `task-item`
- [x] Feature `tasks` com lazy loading
- [x] Variáveis de ambiente para URL da API
- [x] Remover fallback de dados fake

### Fase 4 — Qualidade

- [x] Testes PHPUnit (API)
- [x] Testes Angular (serviço)
- [ ] Laravel Pint + ESLint

### Fase 5 — Responsividade e UX

- [x] SCSS com breakpoints (mobile-first)
- [x] Layout flex/grid adaptativo
- [x] Loading, empty state e mensagens de erro

---

## 5. Ordem de execução

```
Fase 0 (Spec) → Fase 1 (Infra) → Fase 2 (Backend) → Testes API
    → Fase 3 (Frontend) → Fase 4 (Qualidade) → Fase 5 (UX)
```

---

## 6. Quick wins (prioridade imediata)

1. Mover lógica de `api.php` para Controller + Use Cases + Repository
2. Trocar JSON por banco de dados
3. Extrair `TaskService` no Angular e tipar com `Task`
4. Corrigir `provideHttpClient()` e variáveis de ambiente
5. Criar `app.component.scss` ou remover referência
6. Adicionar testes mínimos para os 3 endpoints

---

## 7. Critérios de conclusão

- [ ] Aplicação funcional (CRUD de tarefas)
- [ ] Código organizado com separação de responsabilidades
- [ ] Testes automatizados passando
- [ ] Interface responsiva
- [ ] Sem más práticas listadas na seção 1
- [ ] Documentação da API (OpenAPI)
