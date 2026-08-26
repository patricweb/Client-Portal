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

## Client documents v2

The portal includes guided document forms, a separate legal-provider profile, versioned signed uploads, archived PDFs, explicit acceptance, and SOW-linked advance/final billing. Start with **Provider settings**, then **Documents → New document (v2)**. See the [operator guide](docs/DOCUMENT_WORKFLOW.md) before issuing real documents. Unknown legal and bank details are deliberately left blank.

## Production deployment

Use [DEPLOYMENT.md](DEPLOYMENT.md) and `docker-compose.production.yml`. Production launch requires an external HTTPS reverse proxy, real SMTP/Telegram/storage credentials, strong secrets, and a verified database restore test.
