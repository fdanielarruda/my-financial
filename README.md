# My Financial

Aplicação de gestão financeira pessoal/familiar construída com Laravel, Inertia.js e Vue 3. Centraliza contas, cartões de crédito, transações e contas recorrentes de várias pessoas, e usa a OpenAI para ajudar a importar e classificar extratos.

## Funcionalidades

- **Contas e pessoas** — acompanhamento de saldos em múltiplas contas, agrupados por pessoa, com suporte a arquivamento de contas antigas.
- **Cartões de crédito e faturas** — gerenciamento de cartões, compras, parcelamentos, pagamento de faturas, divisão de parcelas e estornos.
- **Transações** — CRUD completo com lançamento rápido, visualização recente por banco, transferências entre contas e divisão de transações.
- **Transações recorrentes** — agendamento de receitas/despesas recorrentes que geram transações futuras automaticamente (comando `GenerateRecurringTransactions`).
- **Importação de extratos** — upload de extratos de banco/cartão com extração automática das transações via OpenAI, para revisão antes de confirmar.
- **Classificação de transações** — categorização assistida por IA, com fluxo de revisão/confirmação.
- **Relatórios e dashboard** — patrimônio líquido, saldos por pessoa, faturas em aberto e transações recorrentes futuras em um só lugar.
- **Valores a receber/pagar** — controle de valores devidos entre pessoas.

## Stack Tecnológica

- **Backend:** PHP 8.3, Laravel 13, Inertia Laravel, Laravel Sanctum
- **Frontend:** Vue 3, Inertia.js, Tailwind CSS, Vite
- **IA:** [openai-php/laravel](https://github.com/openai-php/laravel) para extração de extratos e classificação de transações
- **Banco de dados:** SQLite por padrão (configurável via `.env`)
- **Testes:** PHPUnit

## Requisitos

- PHP >= 8.3 com as extensões exigidas pelo Laravel
- Composer
- Node.js e npm
- Uma chave de API da OpenAI (para as funcionalidades de importação de extratos e classificação de transações)

## Como começar

```bash
# Instalar dependências PHP
composer install

# Copiar o arquivo de ambiente e gerar a chave da aplicação
cp .env.example .env
php artisan key:generate

# Configurar o banco de dados (SQLite por padrão)
touch database/database.sqlite
php artisan migrate

# Instalar dependências JS
npm install
```

Configure suas credenciais da OpenAI no `.env`:

```
OPENAI_API_KEY=sua-chave-aqui
OPENAI_MODEL=gpt-5
```

### Rodando a aplicação

```bash
composer dev
```

Esse comando roda simultaneamente o servidor PHP, o listener da fila, o log tailer (Pail) e o servidor de desenvolvimento do Vite.

Alternativamente, rode cada parte separadamente:

```bash
php artisan serve
php artisan queue:listen
npm run dev
```

## Comandos agendados

- `php artisan app:generate-recurring-transactions` — gera transações a partir das regras recorrentes ativas.
- `php artisan app:close-credit-card-invoices` — fecha faturas de cartão de crédito cujo ciclo já terminou.

Certifique-se de que o scheduler do Laravel esteja rodando em produção (`php artisan schedule:run` via cron) para que esses comandos sejam executados automaticamente.

## Testes

```bash
composer test
```

## Estrutura do projeto

- `app/Models` — models Eloquent (Account, Transaction, CreditCard, Person, RecurringTransaction, etc.)
- `app/Http/Controllers/Finance` — controllers dos recursos financeiros
- `app/Services/StatementImport` — lógica de extração de extratos (assistida por IA)
- `app/Services/TransactionClassification` — lógica de categorização de transações (assistida por IA)
- `resources/js/Pages/Finance` — páginas Vue do módulo financeiro
- `routes/finance.php` — rotas relacionadas ao módulo financeiro

## Licença

Este é um projeto pessoal privado. Não há licença concedida para reutilização.
