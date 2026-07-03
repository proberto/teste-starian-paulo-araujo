# Critérios de Aceite — Todo List

## Funcionalidades

### Listar tarefas
- [ ] `GET /api/v1/tarefas` retorna status 200
- [ ] Resposta é um array JSON de tarefas
- [ ] Cada tarefa contém: `id`, `title`, `completed`, `created_at`
- [ ] Lista vazia retorna `[]`

### Criar tarefa
- [ ] `POST /api/v1/tarefas` com `{ "title": "..." }` retorna status 201
- [ ] Tarefa criada tem `completed: false` por padrão
- [ ] Título vazio retorna status 422
- [ ] Título com mais de 255 caracteres retorna status 422
- [ ] Título ausente retorna status 422

### Remover tarefa
- [ ] `DELETE /api/v1/tarefas/{id}` com ID existente retorna status 204
- [ ] `DELETE /api/v1/tarefas/{id}` com ID inexistente retorna status 404
- [ ] Tarefa removida não aparece mais na listagem

## Frontend

- [ ] Interface exibe lista de tarefas carregada da API
- [ ] Usuário pode adicionar nova tarefa via formulário
- [ ] Usuário pode remover tarefa existente
- [ ] Mensagem exibida quando lista está vazia
- [ ] Mensagem de erro exibida quando API falha (sem dados fake)
- [ ] Layout responsivo em mobile, tablet e desktop

## Qualidade de código

- [ ] Backend segue Clean Architecture (Controller → Use Case → Repository)
- [ ] Frontend separado em feature modules com serviço dedicado
- [ ] Tipagem forte (sem `any`)
- [ ] Testes automatizados para endpoints da API
- [ ] Persistência em banco de dados (não JSON)
