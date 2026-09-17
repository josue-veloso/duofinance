# DuoFinance

Aplicativo de controle financeiro para casais. O objetivo principal é calcular quem deve quanto para quem no final do mês, gerando uma única transferência de acerto (o "veredito") em vez de várias cobranças e repasses ao longo dos dias.

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-4169E1?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?style=flat-square&logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?style=flat-square&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![PHPUnit](https://img.shields.io/badge/Tests-25%20passed-success?style=flat-square)](https://phpunit.de/)

<!-- Para exibir uma prévia visual no repositório, salve uma captura em docs/preview.png e descomente a linha abaixo -->
<!-- ![Preview do DuoFinance](docs/preview.png) -->

---

## O Problema que Resolve

Quando duas pessoas dividem contas, geralmente cada uma paga despesas diferentes ao longo do mês: um passa as compras de mercado no cartão, o outro paga a conta de energia, o aluguel é dividido meio a meio e um jantar foi combinado em proporções diferentes.

Fazer transferências a cada compra gera confusão e retrabalho. O DuoFinance consolida os lançamentos do mês, desconta o que cada um já pagou da sua respectiva parte e calcula apenas o saldo final. Se o Usuário A pagou R$ 1.500 de despesas compartilhadas e sua cota era de R$ 1.000, e o Usuário B pagou R$ 500 e sua cota era R$ 1.000, o sistema resume tudo a: **Usuário B transfere R$ 500 para o Usuário A**.

---

## Como o Acerto é Calculado

No código da aplicação, essa rotina de conciliação é chamada de *clearing*.

Para cada parcela de despesa compartilhada do mês, o sistema calcula quanto coube a cada parceiro de acordo com a divisão escolhida no lançamento:

- **Padrão do Casal**: usa a proporção cadastrada na conta (ex: 50%/50% ou proporcional à renda).
- **50/50**: meio a meio para aquele gasto específico.
- **Personalizada**: porcentagem definida na hora do cadastro (ex: 70%/30%).
- **Integral**: 100% atribuído a um dos parceiros.

No fechamento:

$$\text{Balanço do Usuário} = \text{Total pago em gastos compartilhados} - \sum \text{Sua parte em cada gasto}$$

- Balanço **positivo**: o usuário pagou mais do que sua cota e tem valor a receber.
- Balanço **negativo**: o usuário pagou menos do que sua cota e precisa transferir a diferença.

Nas contas compartilhadas, o valor que um parceiro tem a receber equivale exatamente ao que o outro tem a transferir ($\text{Balanço}_A + \text{Balanço}_B = 0$). Despesas puramente pessoais ficam registradas no histórico individual de cada um, sem interferir no acerto mútuo.

---

## Regras de Negócio Implementadas

- **Aprovação mútua em gastos compartilhados**: Gastos individuais entram confirmados diretamente. Gastos marcados como compartilhados entram com status pendente e exigem que o parceiro aprove ou recuse. O fechamento do mês fica bloqueado enquanto houver itens pendentes.
- **Fechamento de mês (bloqueio de alterações)**: Após fechar o mês e registrar o acerto, os lançamentos daquele período ficam travados. Tentativas de criar, editar ou excluir gastos em meses fechados retornam erro `422`.
- **Tratamento de centavos no parcelamento**: Em compras divididas em várias vezes (ex: R$ 100 em 3x), a última parcela absorve a diferença de arredondamento (R$ 33,33 + R$ 33,33 + R$ 33,34) para que a soma das parcelas bata exatamente com o valor da compra.
- **Histórico e reversão (undo)**: Cada criação, edição, exclusão ou restauração gera um registro na tabela `expense_audits` com o estado anterior e o novo, permitindo desfazer a última alteração.
- **Exclusão suave (Soft Deletes)**: Despesas excluídas mantêm registro no banco (`deleted_at`) e podem ser restauradas.
- **Despesas recorrentes**: Contas fixas (como aluguel ou internet) podem ser cadastradas direto no formulário de despesa e são geradas mensalmente via comando agendado (`php artisan expenses:materialize-recurring`).

---

## Organização do Backend

O backend separa a camada HTTP das regras de negócio:

- **Controllers**: recebem as requisições, chamam os serviços e retornam as respostas.
- **Form Requests**: concentram a validação dos dados de entrada antes de chegar ao controller.
- **Services**: concentram a lógica de negócio.
  - `ExpenseService`: criação, parcelamento, edição, soft delete, restauração e auditoria de despesas.
  - `ClearingService`: cálculo do veredito do mês, histórico de fechamento e registro de quitação de dívidas.
- **Resources**: formatam a resposta JSON enviada para o frontend.
- **Consultas no banco**: O cálculo do balanço mensal é feito com agregações SQL no PostgreSQL (`SUM(CASE WHEN...)`), evitando carregar todas as despesas para a memória da aplicação.
- **Índice composto**: Foi adicionado índice em `installments(due_month, expense_id)` para consultas das parcelas por competência.

---

## Stack

- **Backend**: PHP 8.3, Laravel 13, PostgreSQL 15, Laravel Sanctum (autenticação).
- **Frontend**: Vue 3 (Composition API, `<script setup>`), TypeScript, Tailwind CSS, Pinia, Lucide Icons, Vite.
- **Tipos no Frontend**: pacote local `@duofinance/shared-types` com os tipos TypeScript que representam as entidades e contratos de API consumidos pela aplicação web.

---

## Estrutura de Pastas

```
duofinance/
├── apps/
│   ├── backend/                     # API Laravel
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/     # ExpenseController, ClearingController, etc.
│   │   │   │   ├── Middleware/      # EnsureUserHasCouple (valida vínculo do casal)
│   │   │   │   ├── Requests/        # Form Requests de validação
│   │   │   │   └── Resources/       # Formatação das respostas JSON
│   │   │   ├── Models/              # Expense, Installment, MonthlyClose, etc.
│   │   │   └── Services/            # ExpenseService, ClearingService
│   │   ├── database/migrations/     # Migrações do banco de dados
│   │   └── tests/Feature/           # Testes de integração
│   └── frontend/                    # SPA Vue 3
│       └── src/
│           ├── components/          # Telas, modais e componentes visuais
│           ├── stores/              # Pinia (auth, expenses, clearing)
│           └── services/            # Cliente HTTP (axios)
└── packages/
    └── shared-types/                # Interfaces TypeScript consumidas pelo frontend
```

---

## Testes Automatizados

O backend possui 25 testes de feature cobrindo os fluxos principais e regras de validação:

```bash
cd apps/backend
php artisan test
```

Cenários cobertos pelos testes:
- Cálculo proporcional padrão do casal.
- Divisão 50/50 e divisão com percentual customizado por despesa.
- Fluxo de aprovação e recusa de gastos compartilhados.
- Bloqueio de fechamento de mês com pendências de aprovação.
- Fechamento com sucesso e bloqueio contra fechamento duplicado.
- Bloqueio de criação, edição e exclusão de gastos em meses fechados.
- Restauração de despesas via soft delete com auditoria.
- Materialização automática de recorrências.
- Middleware que impede operações financeiras de usuários sem vínculo de casal.

---

## Como Rodar Localmente

### Pré-requisitos
- Docker
- PHP 8.3 e Composer
- Node.js 20+ e npm

### 1. Instalar dependências

```bash
git clone https://github.com/seu-usuario/duofinance.git
cd duofinance

# Dependências do frontend e monorepo
npm install

# Dependências do backend
cd apps/backend
composer install
```

### 2. Subir o PostgreSQL via Docker

```bash
docker run -d \
  --name duofinance-db \
  -p 5432:5432 \
  -e POSTGRES_DB=duofinance \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD=postgres \
  postgres:15
```

### 3. Configurar variáveis e rodar migrações

```bash
cd apps/backend
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

O seed cria as categorias básicas e dois usuários de teste:
- `josue@duofinance.dev` (senha: `senha123`)
- `gabriela@duofinance.dev` (senha: `senha123`)

### 4. Iniciar os servidores

Na raiz do projeto:

```bash
# Terminal 1 - Backend (porta 3333)
npm run dev:back

# Terminal 2 - Frontend (porta 5173)
npm run dev:front
```

Acesse: `http://localhost:5173`

---

## Endpoints Principais

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/api/auth/register` | Cadastro de usuário |
| `POST` | `/api/auth/login` | Login e geração de token |
| `POST` | `/api/couples/link` | Vincula os dois usuários do casal e define as cotas |
| `GET` | `/api/expenses` | Lista despesas e parcelas filtradas por mês |
| `POST` | `/api/expenses` | Cria gasto (com suporte a parcelamento, divisão e recorrência) |
| `PUT` | `/api/expenses/{id}` | Edita gasto existente (bloqueado se o mês estiver fechado) |
| `DELETE`| `/api/expenses/{id}` | Remove gasto (soft delete) |
| `POST` | `/api/expenses/{id}/restore` | Restaura gasto removido |
| `POST` | `/api/expenses/{id}/approve` | Parceiro aprova gasto compartilhado |
| `POST` | `/api/expenses/{id}/reject` | Parceiro recusa gasto compartilhado |
| `POST` | `/api/expenses/{id}/undo` | Desfaz a última alteração via log de auditoria |
| `GET` | `/api/clearing/summary` | Retorna o resumo financeiro e o veredito do mês |
| `POST` | `/api/clearing/close` | Realiza o fechamento do mês |
| `POST` | `/api/clearing/debts/payments` | Registra pagamento/quitação do valor devido |
