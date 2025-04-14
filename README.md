
# FINAL API de Gerenciamento de Viagens Corporativas

API REST para gerenciamento de pedidos de viagem corporativa desenvolvida com Laravel 11+ e Docker.

## Tecnologias Utilizadas

-   PHP 8.2+
-   Laravel 11+
-   MySQL 8.0
-   Docker & Docker Compose
-   Tymon JWT-Auth para autenticação

## Pré-requisitos

-   Git
-   Docker ([https://docs.docker.com/get-docker/](https://docs.docker.com/get-docker/))
-   Docker Compose (geralmente incluído com Docker Desktop ou instalável separadamente no Linux: [https://docs.docker.com/compose/install/](https://docs.docker.com/compose/install/))
-   Opcional: VS Code com a extensão [Remote - Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) para uma melhor experiência de desenvolvimento.

## Configuração e Execução Rápida com Docker

Estas instruções assumem que você tem Docker e Docker Compose instalados e funcionando.

1.  **Clone o Repositório:**
    ```bash
    git clone git@github.com:JonathanLemke/travel-api-challenge.git travel-api
    cd travel-api
    ```

2.  **Arquivo de Ambiente (`.env`):**
    *   **Caso este repositório inclua um arquivo `.env`:** Este passo pode ser pulado, pois o arquivo `.env` necessário para o ambiente Docker já está presente (foi incluído especificamente para facilitar a configuração deste desafio).
    *   **Caso `.env` não exista:** Copie o arquivo de exemplo:
        ```bash
        cp .env.example .env
        ```
        *   Neste caso, você **precisará** gerar as chaves após iniciar os containers (veja passos posteriores).

3.  **Construa e Inicie os Containers Docker:**
    Este comando irá construir a imagem da aplicação (se necessário) e iniciar todos os serviços (`app`, `nginx`, `db`) em background.
    ```bash
    docker-compose up -d --build
    ```

4.  **Instale as Dependências do Composer:**
    Execute o Composer *dentro* do container `app` para instalar as bibliotecas PHP necessárias.
    ```bash
    docker-compose exec app composer install --no-interaction --optimize-autoloader
    ```

5.  **Gere as Chaves (SE `.env` NÃO FOI COMITADO/COPIADO no Passo 2):**
    *   Se você copiou do `.env.example` no passo 2, execute os seguintes comandos para gerar as chaves de aplicação e JWT:
        ```bash
        docker-compose exec app php artisan key:generate
        docker-compose exec app php artisan jwt:secret
        ```

6.  **Execute as Migrations do Banco de Dados:**
    Cria a estrutura das tabelas no banco de dados MySQL dentro do container `db`.
    ```bash
    docker-compose exec app php artisan migrate
    ```
    *(Para popular o banco com dados de exemplo, você precisaria criar Seeders e rodar `php artisan migrate --seed`).*

7.  **Pronto!** A API deve estar acessível na sua máquina local:
    *   **URL Base:** `http://localhost:8000`
    *   **Prefixo da API:** `/api` (Ex: `http://localhost:8000/api/login`)

## Executando os Testes

Para rodar a suíte completa de testes automatizados (PHPUnit) e verificar a integridade da aplicação:

```bash
docker-compose exec app php artisan test
```

## Endpoints da API

### Endpoints de Autenticação

-   **`POST /api/register`**
    -   Descrição: Registra um novo usuário (com role 'user' por padrão).
    -   Body (JSON): `{ "name": "Seu Nome", "email": "email@exemplo.com", "password": "sua_senha_min_6" }`
    -   Resposta: Token JWT e dados do usuário.
-   **`POST /api/login`**
    -   Descrição: Autentica um usuário existente.
    -   Body (JSON): `{ "email": "email@exemplo.com", "password": "sua_senha" }`
    -   Resposta: Token JWT e dados do usuário (em caso de sucesso), erro 401 (em caso de falha).
-   **`POST /api/logout`**
    -   Descrição: Invalida o token JWT atual do usuário.
    -   Autenticação: Requer `Authorization: Bearer <token>` no header.
    -   Resposta: Mensagem de sucesso.
-   **`POST /api/refresh`**
    -   Descrição: Gera um novo token JWT, invalidando o antigo. Útil para manter a sessão ativa.
    -   Autenticação: Requer `Authorization: Bearer <token>` no header (pode ser um token expirado, mas não inválido).
    -   Resposta: Novo Token JWT e dados do usuário.
-   **`GET /api/me`**
    -   Descrição: Retorna os dados do usuário autenticado atualmente.
    -   Autenticação: Requer `Authorization: Bearer <token>` no header.
    -   Resposta: Dados do usuário autenticado.

### Endpoints de Pedidos de Viagem (Requerem Token JWT)

-   **`GET /api/travel-requests`**
    -   Descrição: Lista os pedidos de viagem. Usuários 'user' veem apenas os seus, 'admin' veem todos.
    -   Autenticação: Requer `Authorization: Bearer <token>`.
    -   Query Params (Opcionais para Filtros):
        -   `status=requested` ou `approved` ou `canceled`
        -   `start_date=YYYY-MM-DD`
        -   `end_date=YYYY-MM-DD`
        -   `destination=TextoParcial`
    -   Resposta: Coleção de pedidos de viagem formatados.
-   **`POST /api/travel-requests`**
    -   Descrição: Cria um novo pedido de viagem para o usuário autenticado (status inicial 'requested').
    -   Autenticação: Requer `Authorization: Bearer <token>`.
    -   Body (JSON): `{ "destination": "Nome do Destino", "departure_date": "YYYY-MM-DD", "return_date": "YYYY-MM-DD" }` (Datas devem ser válidas e futuras).
    -   Resposta: O pedido de viagem recém-criado e formatado.
-   **`GET /api/travel-requests/{id}`**
    -   Descrição: Consulta os detalhes de um pedido de viagem específico. Usuários 'user' só podem ver os seus.
    -   Autenticação: Requer `Authorization: Bearer <token>`.
    -   Parâmetro de Rota: `{id}` do pedido.
    -   Resposta: Detalhes do pedido formatado (200), erro 403 (não autorizado) ou 404 (não encontrado).
-   **`PATCH /api/travel-requests/{id}/status`**
    -   Descrição: Atualiza o status de um pedido de viagem (apenas para 'approved' ou 'canceled'). **Requer permissão de 'admin'.**
    -   Autenticação: Requer `Authorization: Bearer <token>` (de um admin).
    -   Parâmetro de Rota: `{id}` do pedido.
    -   Body (JSON): `{ "status": "approved" }` ou `{ "status": "canceled" }`
    -   Resposta: O pedido atualizado e formatado (200), erro 403 (não admin), 404 (não encontrado) ou 422 (falha na regra de negócio, ex: cancelamento inválido).

## Funcionalidades Implementadas

-   ✅ Criação e listagem de pedidos de viagem.
-   ✅ Consulta de pedido específico.
-   ✅ Atualização de status (aprovação/cancelamento) por admin.
-   ✅ Autenticação de API usando JWT (`tymon/jwt-auth`).
-   ✅ Registro, Login, Logout, Refresh de token.
-   ✅ Proteção de rotas com middleware JWT.
-   ✅ Autorização baseada em Roles ('user', 'admin') para visualização e alteração de status.
-   ✅ Filtragem de pedidos por status, período de datas e destino.
-   ✅ Regra de negócio para cancelamento (pedido aprovado só pode ser cancelado >= 2 dias antes da partida).
-   ✅ Notificações por E-mail (usando fila `database`) para o solicitante na aprovação/cancelamento.
-   ✅ Validação de dados de entrada robusta usando Form Requests.
-   ✅ Testes automatizados (PHPUnit) cobrindo autenticação, CRUD parcial, permissões, filtros, regras de negócio e notificações.
-   ✅ Ambiente de desenvolvimento e execução Dockerizado com Docker Compose.

## Decisões de Projeto

-   **Framework:** Laravel 11+ pela robustez e ecossistema.
-   **Autenticação:** JWT (via `tymon/jwt-auth`) conforme solicitado, para APIs stateless.
-   **Banco de Dados:** MySQL 8.0, relacional padrão.
-   **Ambiente:** Docker e Docker Compose para portabilidade e consistência. Configuração para VS Code Dev Containers (`.devcontainer/devcontainer.json`) incluída para facilitar o desenvolvimento.
-   **Validação:** Form Requests do Laravel para centralizar e reutilizar regras de validação.
-   **Formatação de Resposta:** API Resources (`JsonResource`) para padronizar a saída JSON da API.
-   **Autorização:** Verificações simples baseadas em role ('admin') no Controller. Para cenários mais complexos, Policies seriam recomendadas.
-   **Cancelamento:** Regra de >= 2 dias antes da partida implementada no Model (`canBeCanceled`), considerada uma interpretação razoável do requisito.
-   **Notificações:** Usando o sistema nativo do Laravel, canal de Email (configurado para `log` no `.env` padrão) e processamento em fila (`database` driver) para melhor performance.
-   **Testes:** Foco em testes de Feature (HTTP) para garantir o funcionamento dos endpoints e regras de negócio de ponta a ponta.

