# Ikira Client Portal

Internal project management and client portal for Ikira Company.

The complete product specification and developer handoff are in [PROJECT_HANDOFF_SPEC.md](PROJECT_HANDOFF_SPEC.md).

## Local requirements

- Docker Desktop with Docker Compose v2
- Git

PHP, Composer, Node.js, MySQL, and Redis run inside Docker. They do not need to be installed on the host.

## First start

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate
```

Open `http://localhost:8080`.

The development owner account is created by the database seeder:

```text
Email: owner@ikira.company
Password: ChangeMe123!
```

Change these values in `.env` before seeding a shared or production environment.

On Windows PowerShell, use this instead of `cp`:

```powershell
Copy-Item .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate
```

## Development commands

```bash
docker compose exec app php artisan test
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan care:generate-invoices
docker compose exec app npm run dev -- --host 0.0.0.0
docker compose logs -f app queue scheduler
./docker/backup.ps1
```

After every `git pull`, synchronize dependencies before migrations:

```bash
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app npm ci --ignore-scripts
docker compose exec app php artisan migrate --force
docker compose exec app npm run build
docker compose restart queue scheduler
```

## Services

- `app`: Apache, PHP, Composer, Node.js, and Laravel
- `mysql`: MySQL 8.0
- `redis`: cache and queues
- `queue`: Laravel queue worker for email and Telegram notifications
- `scheduler`: scheduled reminders, overdue invoices, and recurring billing jobs

Default ports can be changed in `.env`:

- Portal: `8080`
- Vite: `5173`
- MySQL: `3308`
- Redis: `6380`

The credentials in `.env.example` are for local development only. Replace all secrets before deployment.

## Business agreements and acceptance

The portal uses a binding **Project Services Agreement**, an optional **Project Change Order**, and a **Delivery Acceptance Record**. Client acceptance is tied to an immutable PDF version, its SHA-256 hash, the representative's authority statement and audit metadata. Invoices remain separate. Start with **Provider settings**, then **Agreements & records → New agreement / record**. See the [operator guide](docs/DOCUMENT_WORKFLOW.md) before issuing real documents.

## Production deployment

Use [DEPLOYMENT.md](DEPLOYMENT.md) and `docker-compose.production.yml`. Production launch requires an external HTTPS reverse proxy, real SMTP/Telegram/storage credentials, strong secrets, and a verified database restore test.

## Internal team dispatcher

The staff-only `Work Items` module replaces the separate Python/JSON order dispatcher. It stores assignments in MySQL, links them to projects and team members, keeps prices hidden from non-financial roles, and synchronizes statuses with private Telegram topics and Discord forums through the Laravel queue. Client accounts cannot access this module.

Configuration and token-rotation instructions: [docs/INTERNAL_WORK_ITEMS.md](docs/INTERNAL_WORK_ITEMS.md).
