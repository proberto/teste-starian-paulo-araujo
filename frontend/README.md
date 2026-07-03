# Frontend — Documentação da Refatoração

Este documento descreve **todas as modificações realizadas no frontend Angular** durante a refatoração do projeto, explicando **o que foi feito** e **por que** cada decisão foi tomada.

---

## Sumário

1. [Contexto e problema original](#contexto-e-problema-original)
2. [Arquitetura adotada](#arquitetura-adotada)
3. [Fluxo de dados na aplicação](#fluxo-de-dados-na-aplicação)
4. [Modificações detalhadas](#modificações-detalhadas)
5. [Arquivos removidos](#arquivos-removidos)
6. [Como executar](#como-executar)
7. [Como testar](#como-testar)

---

## Contexto e problema original

O frontend original era um único componente monolítico (`AppComponent`) que concentrava toda a aplicação. As principais más práticas eram:

| Problema | Impacto |
|----------|---------|
| **God Component** — `AppComponent` com UI, estado, HTTP e lógica | Difícil de manter, testar e escalar |
| Uso de `any[]` e `any` | Sem type safety; erros só aparecem em runtime |
| `HttpClient` injetado diretamente no componente | Acoplamento forte; impossível mockar facilmente |
| `HttpClientModule` importado no componente | Padrão desatualizado no Angular 17+ |
| URL da API hardcoded (`http://localhost:8000/tarefas`) | Não funciona em outros ambientes sem alterar código |
| Estilos inline no HTML (`style="..."`) | Impossível reutilizar, difícil de manter responsividade |
| Referência a `app.component.scss` inexistente | Erro de build/compilação |
| Fallback com dados fake em caso de erro | Mascara falhas reais da API |
| `router-outlet` sem rotas configuradas | Roteamento morto, sem lazy loading |
| Largura fixa (`width: 300px`) | Interface não responsiva |
| Sem testes | Nenhuma garantia de regressão |

A refatoração transformou esse monólito em uma aplicação modular, tipada, testável e responsiva.

---

## Arquitetura adotada

Foi aplicada uma arquitetura **Feature-Sliced** com separação em camadas:

```
app/
├── core/           → serviços singleton globais (config da API)
├── shared/         → modelos e utilitários compartilhados
└── features/
    └── tasks/      → feature completa de tarefas
        ├── components/   → UI (apresentação)
        ├── services/     → comunicação HTTP
        └── tasks.component.ts  → smart component (estado + orquestração)
```

### Princípios aplicados

**SOLID**

- **S (Single Responsibility):**
  - `TaskFormComponent` — apenas o formulário de criação
  - `TaskListComponent` — apenas a listagem
  - `TaskItemComponent` — apenas um item individual
  - `TasksComponent` — orquestra estado e chama o serviço
  - `TaskService` — apenas chamadas HTTP

- **O (Open/Closed):** novas features (ex.: `auth`, `settings`) podem ser adicionadas sem alterar `core` ou `shared`.

- **D (Dependency Inversion):** componentes dependem de `TaskService`, não de `HttpClient` diretamente. O serviço abstrai a comunicação com a API.

**DRY (Don't Repeat Yourself)**

- `TaskService` centraliza todas as chamadas HTTP (GET, POST, DELETE).
- Interfaces `Task` e `CreateTaskRequest` definem o contrato de dados em um único lugar.
- Estilos SCSS com convenção BEM (`task-form__input`, `task-item__title`) evitam repetição.

**Spec-Driven Development**

- O frontend consome a API definida em `specs/openapi.yaml` na raiz do projeto.
- Os testes do `TaskService` validam que as chamadas HTTP seguem o contrato (método, URL, body).

### Padrão Smart vs Dumb Components

| Tipo | Componente | Responsabilidade |
|------|-----------|------------------|
| **Smart** | `TasksComponent` | Estado (`tasks`, `loading`, `error`), chama serviços, reage a eventos |
| **Dumb** | `TaskFormComponent` | Recebe nada; emite `taskSubmit` com o título |
| **Dumb** | `TaskListComponent` | Recebe `tasks` via `@Input`; emite `taskRemove` |
| **Dumb** | `TaskItemComponent` | Recebe `task` via `@Input`; emite `taskRemove` com o ID |

**Por quê:** componentes dumb são puros, fáceis de testar e reutilizar. O smart component concentra a lógica de negócio.

---

## Fluxo de dados na aplicação

Exemplo: usuário adiciona uma nova tarefa

```
1. TaskFormComponent
   → usuário digita título e clica "Adicionar"
   → emite (taskSubmit)="onTaskSubmit($event)"

2. TasksComponent.onTaskSubmit(title)
   → chama taskService.createTask({ title })

3. TaskService
   → POST http://localhost:8000/api/v1/tarefas
   → retorna Observable<Task>

4. TasksComponent (subscribe next)
   → adiciona a nova tarefa no início do array: [task, ...tasks]

5. TaskListComponent
   → recebe [tasks] atualizado via @Input
   → renderiza TaskItemComponent para cada tarefa
```

Em caso de erro na API:

```
TaskService (subscribe error)
  → TasksComponent define error = "Não foi possível..."
  → template exibe mensagem com role="alert"
  → NÃO cria dados fake (comportamento antigo removido)
```

---

## Modificações detalhadas

### Shell da aplicação

#### `app/app.component.ts` — simplificado

**Antes:** continha toda a lógica — lista de tarefas, formulário, chamadas HTTP, tratamento de erro com dados fake, estilos inline no template.

**Depois:** shell mínimo com apenas `<router-outlet />`:

```typescript
@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet],
  template: '<router-outlet />',
})
export class AppComponent {}
```

**Por quê:** o `AppComponent` deve ser apenas o ponto de entrada da aplicação. Toda funcionalidade foi movida para a feature `tasks` via roteamento.

---

#### `app/app.component.html` — removido

**Antes:** template com 45 linhas de HTML, estilos inline e lógica de apresentação.

**Depois:** removido; o template inline no `.ts` é suficiente para o shell.

**Por quê:** elimina o arquivo que misturava apresentação com o componente raiz e remove a referência ao `app.component.scss` inexistente.

---

#### `app/app.config.ts` — atualizado

**Antes:** apenas `provideRouter(routes)` — sem HTTP configurado globalmente.

**Depois:**

```typescript
export const appConfig: ApplicationConfig = {
  providers: [
    provideRouter(routes),
    provideHttpClient(),
  ],
};
```

**Por quê:**
- `provideHttpClient()` é o padrão recomendado no Angular 17+ (substitui `HttpClientModule`).
- Configuração centralizada: qualquer serviço pode injetar `HttpClient` sem importar módulos em cada componente.
- Remove a necessidade de `HttpClientModule` no `AppComponent`.

---

#### `app/app.routes.ts` — configurado com lazy loading

**Antes:** array vazio (`Routes = []`).

**Depois:**

```typescript
export const routes: Routes = [
  {
    path: '',
    loadChildren: () =>
      import('./features/tasks/tasks.routes').then((m) => m.tasksRoutes),
  },
  {
    path: '**',
    redirectTo: '',
  },
];
```

**Por quê:**
- Lazy loading carrega a feature `tasks` sob demanda — reduz o bundle inicial.
- `path: '**'` redireciona rotas inválidas para a home.
- Prepara a aplicação para novas features sem aumentar o bundle principal.

---

### Camada Core

#### `app/core/services/api-config.service.ts` — criado

Serviço singleton que expõe a URL base da API a partir do environment:

```typescript
@Injectable({ providedIn: 'root' })
export class ApiConfigService {
  readonly apiUrl = environment.apiUrl;
}
```

**Por quê:**
- Centraliza a configuração da API em um único ponto.
- Outros serviços (`TaskService`) dependem deste serviço, não do `environment` diretamente — facilita testes (mock do `ApiConfigService`).
- Segue o princípio de Dependency Inversion.

---

### Camada Shared

#### `app/shared/models/task.model.ts` — criado

Interfaces TypeScript que espelham o contrato da API:

```typescript
export interface Task {
  id: number;
  title: string;
  completed: boolean;
  created_at: string;
}

export interface CreateTaskRequest {
  title: string;
}
```

**Por quê:**
- Substitui `any[]` e `any` por tipos explícitos — erros de tipo são detectados em compile time.
- Documenta o contrato de dados compartilhado entre serviço e componentes.
- Alinhado com `specs/openapi.yaml` e com o `TaskResource` do backend.

---

### Feature Tasks

#### `app/features/tasks/tasks.routes.ts` — criado

Rotas internas da feature com lazy loading do componente:

```typescript
export const tasksRoutes: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./tasks.component').then((m) => m.TasksComponent),
  },
];
```

**Por quê:** cada feature gerencia suas próprias rotas. O `TasksComponent` é carregado apenas quando o usuário acessa a rota.

---

#### `app/features/tasks/tasks.component.ts` — criado (Smart Component)

Orquestra o estado e a lógica da feature:

| Propriedade | Tipo | Descrição |
|-------------|------|-----------|
| `tasks` | `Task[]` | Lista de tarefas carregada da API |
| `loading` | `boolean` | Indica carregamento em andamento |
| `error` | `string \| null` | Mensagem de erro para exibir ao usuário |

| Método | Ação |
|--------|------|
| `loadTasks()` | GET na API; define `loading` e trata erro |
| `onTaskSubmit(title)` | POST na API; adiciona tarefa ao array |
| `onTaskRemove(id)` | DELETE na API; remove tarefa do array |

**Por quê:**
- Concentra a lógica que antes estava no `AppComponent`.
- Injeta `TaskService` (não `HttpClient`) — testável via mock do serviço.
- Em erro, exibe mensagem real ao usuário em vez de criar dados fake.

---

#### `app/features/tasks/tasks.component.html` — criado

Template declarativo que compõe os subcomponentes:

```html
<app-task-form (taskSubmit)="onTaskSubmit($event)" />
<p *ngIf="loading">Carregando tarefas...</p>
<p *ngIf="error" role="alert">{{ error }}</p>
<app-task-list [tasks]="tasks" (taskRemove)="onTaskRemove($event)" />
```

**Por quê:**
- Separação clara: formulário, feedback (loading/erro) e listagem.
- `role="alert"` melhora acessibilidade para leitores de tela.
- Lista só é exibida quando não está carregando (`*ngIf="!loading"`).

---

#### `app/features/tasks/tasks.component.scss` — criado

Estilos do container principal com responsividade:

- `max-width: 640px` — limita largura em telas grandes
- `padding` adaptativo (1.25rem mobile → 2rem desktop)
- Título com `font-size` responsivo (1.75rem → 2rem)
- Mensagem de erro com fundo vermelho claro e bordas arredondadas

**Por quê:** substitui estilos inline por SCSS com breakpoints, atendendo ao requisito de responsividade do teste.

---

#### `app/features/tasks/services/task.service.ts` — criado

Única fonte de comunicação HTTP com a API de tarefas:

| Método | HTTP | Endpoint |
|--------|------|----------|
| `getTasks()` | GET | `/api/v1/tarefas` |
| `createTask(request)` | POST | `/api/v1/tarefas` |
| `deleteTask(id)` | DELETE | `/api/v1/tarefas/{id}` |

Retorna `Observable<T>` tipado — sem `any`.

**Por quê (DRY + DIP):**
- Toda chamada HTTP passa por um único serviço — se a URL mudar, altera-se em um lugar.
- Componentes não conhecem `HttpClient` — dependem da abstração `TaskService`.
- Tipagem forte com `Observable<Task[]>` e `Observable<Task>`.

A URL base vem do `ApiConfigService`, que lê o `environment.apiUrl` — não está hardcoded no serviço.

---

#### `app/features/tasks/services/task.service.spec.ts` — criado

3 testes unitários do `TaskService` usando `HttpClientTestingModule`:

| Teste | Valida |
|-------|--------|
| `should fetch tasks` | GET na URL correta, retorna array tipado |
| `should create a task` | POST com body `{ title }`, retorna Task |
| `should delete a task` | DELETE em `/tarefas/{id}` |

**Por quê:** garante que o serviço faz as chamadas HTTP corretas sem precisar da API real (Spec-Driven).

---

### Componentes de apresentação (Dumb)

#### `app/features/tasks/components/task-form/` — criado

**Responsabilidade:** capturar o título e emitir evento.

- `@Output() taskSubmit` — emite o título trimado
- Validação local: não emite se título vazio
- Limpa o campo após submit
- `aria-label` no input para acessibilidade

**Estilos (`task-form.component.scss`):**
- Mobile: coluna (input acima do botão)
- Desktop (`min-width: 480px`): linha (input e botão lado a lado)
- `min-width: 0` no input evita overflow em flex containers

**Por quê:** componente reutilizável e testável isoladamente. Não sabe nada sobre API ou estado global.

---

#### `app/features/tasks/components/task-list/` — criado

**Responsabilidade:** exibir a lista de tarefas ou mensagem de vazio.

- `@Input({ required: true }) tasks` — recebe array tipado
- `@Output() taskRemove` — repassa evento do item para o pai
- Exibe "Nenhuma tarefa encontrada." quando `tasks.length === 0`

**Por quê:** separa a lógica de listagem da lógica de cada item. O componente pai (`TasksComponent`) controla os dados.

---

#### `app/features/tasks/components/task-item/` — criado

**Responsabilidade:** renderizar um único item de tarefa.

- `@Input({ required: true }) task` — recebe objeto `Task` tipado
- `@Output() taskRemove` — emite o `id` ao clicar em "Remover"
- Classe CSS `--completed` para título riscado quando `task.completed`
- `word-break: break-word` — títulos longos não quebram o layout
- `aria-label="Remover tarefa"` no botão

**Por quê:** menor unidade de UI; fácil de testar e estilizar independentemente.

---

### Environments e configuração

#### `src/environments/environment.ts` — criado

```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api/v1',
};
```

#### `src/environments/environment.development.ts` — criado

Mesma estrutura, usado em desenvolvimento via `fileReplacements` no `angular.json`.

**Por quê:**
- Substitui a URL hardcoded (`http://localhost:8000/tarefas`) que existia no `AppComponent`.
- Permite configurar URLs diferentes por ambiente (dev, staging, produção) sem alterar código.
- A URL agora inclui o prefixo `/api/v1` alinhado com o backend refatorado.

---

#### `angular.json` — atualizado

Adicionado `fileReplacements` na configuração `development`:

```json
"fileReplacements": [
  {
    "replace": "src/environments/environment.ts",
    "with": "src/environments/environment.development.ts"
  }
]
```

**Por quê:** o Angular substitui automaticamente o arquivo de environment durante `ng serve` (modo development), permitindo configurações distintas por ambiente.

---

### Estilos globais

#### `src/styles.scss` — atualizado

**Antes:** arquivo vazio (apenas comentário padrão do CLI).

**Depois:**

```scss
*, *::before, *::after { box-sizing: border-box; }

body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', ...;
  background-color: #f5f5f5;
  color: #333;
  line-height: 1.5;
}
```

**Por quê:**
- `box-sizing: border-box` — padding e border incluídos na largura dos elementos.
- Reset de `margin` no body.
- Font stack nativa do sistema — boa legibilidade sem carregar fontes externas.
- Fundo cinza claro para contraste com os cards brancos das tarefas.

---

### Docker

#### `Dockerfile` — atualizado

**Antes:** `WORKDIR /frontend` (inconsistente com o volume do compose).

**Depois:** `WORKDIR /app`, alinhado com o `docker-compose.yml`.

**Por quê:** o volume `./frontend:/app` monta o código no `/app` — o `WORKDIR` deve corresponder.

---

#### `.dockerignore` — criado

```
node_modules
dist
.angular
coverage
```

**Por quê:** evita copiar `node_modules` do host (macOS) para a imagem Docker (Linux). Sem isso, ocorre o erro:

```
Cannot find module @rollup/rollup-linux-arm64-gnu
```

O `node_modules` do Mac tem binários `darwin`, incompatíveis com o container Linux ARM64.

---

#### `docker-compose.yml` (raiz do projeto) — volume isolado para node_modules

```yaml
volumes:
  - ./frontend:/app
  - frontend_node_modules:/app/node_modules
command: sh -c "npm install && npm run start -- --host=0.0.0.0"
```

**Por quê:**
- O volume nomeado `frontend_node_modules` mantém dependências Linux dentro do container.
- `npm install` no startup garante que pacotes estejam instalados para a plataforma correta.
- O código-fonte continua montado do host para hot reload.

---

## Arquivos removidos

| Arquivo | Motivo da remoção |
|---------|-------------------|
| `app/app.component.html` | Lógica movida para `features/tasks/`; template inline no shell |
| Referência a `app.component.scss` | Arquivo nunca existiu; estilos movidos para componentes individuais |

### Comportamentos removidos do `AppComponent` original

| Comportamento antigo | Substituído por |
|---------------------|-----------------|
| `todos: any[]` | `tasks: Task[]` tipado |
| `apiUrl = 'http://localhost:8000/tarefas'` hardcoded | `environment.apiUrl` via `ApiConfigService` |
| `HttpClient` direto no componente | `TaskService` injetado |
| `HttpClientModule` no componente | `provideHttpClient()` no `app.config.ts` |
| Dados fake em caso de erro da API | Mensagem de erro real ao usuário |
| Estilos inline no HTML | SCSS com BEM e breakpoints |
| Toda UI no `AppComponent` | Feature `tasks` com 4 componentes |

---

## Como executar

### Via Docker (recomendado)

Na raiz do projeto:

```bash
docker compose up --build
```

Frontend disponível em: http://localhost:4200

### Localmente

```bash
cd frontend
npm install
npm start
```

Acesse: http://localhost:4200

> **Nota:** a API backend deve estar rodando em `http://localhost:8000` para o frontend funcionar.

---

## Como testar

### Testes unitários

```bash
cd frontend
npm test
```

Ou em modo headless (CI):

```bash
npx ng test --no-watch --browsers=ChromeHeadless
```

Resultado esperado: **3 testes passando** (TaskService: fetch, create, delete).

### Build de produção

```bash
npm run build
```

Verifica que a aplicação compila sem erros. O lazy loading gera chunks separados:

```
Lazy chunk files  | Names           | Raw size
chunk-*.js        | tasks-component | ~17 kB
chunk-*.js        | tasks-routes    | ~241 bytes
```

---

## Estrutura final de pastas

```
frontend/src/
├── app/
│   ├── core/
│   │   └── services/
│   │       └── api-config.service.ts
│   ├── shared/
│   │   └── models/
│   │       └── task.model.ts
│   ├── features/
│   │   └── tasks/
│   │       ├── components/
│   │       │   ├── task-form/
│   │       │   │   ├── task-form.component.ts
│   │       │   │   ├── task-form.component.html
│   │       │   │   └── task-form.component.scss
│   │       │   ├── task-list/
│   │       │   │   ├── task-list.component.ts
│   │       │   │   ├── task-list.component.html
│   │       │   │   └── task-list.component.scss
│   │       │   └── task-item/
│   │       │       ├── task-item.component.ts
│   │       │       ├── task-item.component.html
│   │       │       └── task-item.component.scss
│   │       ├── services/
│   │       │   ├── task.service.ts
│   │       │   └── task.service.spec.ts
│   │       ├── tasks.component.ts
│   │       ├── tasks.component.html
│   │       ├── tasks.component.scss
│   │       └── tasks.routes.ts
│   ├── app.component.ts
│   ├── app.config.ts
│   └── app.routes.ts
├── environments/
│   ├── environment.ts
│   └── environment.development.ts
├── styles.scss
└── main.ts
```

---

## Comparação antes vs depois

| Aspecto | Antes | Depois |
|---------|-------|--------|
| Componentes | 1 (God Component) | 5 (shell + smart + 3 dumb) |
| Tipagem | `any[]`, `any` | `Task`, `CreateTaskRequest` |
| HTTP | Direto no componente | `TaskService` centralizado |
| Configuração API | Hardcoded | `environment` + `ApiConfigService` |
| Rotas | Vazias | Lazy loading da feature `tasks` |
| Estilos | Inline no HTML | SCSS com BEM e breakpoints |
| Erro da API | Dados fake silenciosos | Mensagem clara ao usuário |
| Testes | Nenhum | 3 testes do `TaskService` |
| Responsividade | Largura fixa 300px | Mobile-first com breakpoints |
| Bundle | Tudo no main | Lazy chunk da feature (~17 kB) |

---

## Próximos passos sugeridos

- [ ] Adicionar `provideHttpClient(withInterceptors([errorInterceptor]))` para tratamento global de erros
- [ ] Usar `async` pipe no template em vez de `.subscribe()` manual
- [ ] Testes de componentes (`TasksComponent`, `TaskFormComponent`)
- [ ] ESLint + Prettier para padronização de código
- [ ] CI/CD com GitHub Actions executando `ng test` e `ng build`
- [ ] Toggle de `completed` na UI (quando o backend tiver `PATCH`)
