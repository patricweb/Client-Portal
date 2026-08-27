# Production deployment

This runbook covers the containerized Ikira Client Portal. The application stack is production-ready, but domain ownership, TLS, provider accounts, credentials, and the owner's final business details must be supplied outside this repository.

## 1. Prepare secrets

Create `.env.production` on the server; never commit it. Start from `.env.example` and set at minimum:

- `APP_ENV=production`, `APP_DEBUG=false`, and the final HTTPS `APP_URL`;
- a generated `APP_KEY` (`php artisan key:generate --show`);
- unique high-entropy `DB_PASSWORD` and `DB_ROOT_PASSWORD`;
- `SESSION_SECURE_COOKIE=true`;
- production SMTP values and an approved sender domain;
- Telegram bot token/chat ID if that channel is enabled;
- S3-compatible credentials when uploads must survive outside the Docker host.

Keep `RUN_MIGRATIONS=false` normally. Enable it only for the first app start of a controlled release, then disable it again.

## 2. Start and verify

```bash
docker compose --env-file .env.production -f docker-compose.production.yml up -d --build
docker compose --env-file .env.production -f docker-compose.production.yml ps
docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan migrate:status
docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan about
```

Put the app behind a trusted HTTPS reverse proxy/load balancer and forward requests to port 8080 (or `APP_PORT`). Verify `/up`, login, password reset, one client workflow, queue processing, scheduler logs, email, Telegram, upload/download, and PDF output.

## 3. Backups

From Windows PowerShell in the repository, create and restore a full archive containing MySQL and all private files:

```powershell
.\docker\backup.ps1
.\docker\restore.ps1 -BackupFile .\backups\ikira-full-YYYYMMDD-HHMMSS.zip -ConfirmRestore
```

Store encrypted copies outside the server. Schedule backups according to the business recovery objective and perform a restore drill before launch and periodically afterward. Restoring replaces the target database and private storage and must be tested in a non-production environment first.

## 4. Release procedure

1. Create a full backup of the database and persistent uploads.
2. Build the exact Git revision and run the complete test suite.
3. Put the portal in maintenance mode for migrations that require it.
4. Run migrations once, restart app/queue/scheduler, and run smoke tests.
5. Monitor HTTP errors, queue failures, mail/Telegram delivery logs, disk usage, MySQL, and Redis.
6. Keep the previous image and a verified backup for rollback.

## 5. Launch gates outside the codebase

- DNS and TLS certificate configured and renewal tested;
- rotated Telegram and Discord bot tokens stored only in the production environment;
- private Telegram work chat/topic, webhook secret and allowed team user IDs configured;
- Discord forum IDs configured and the bot limited to the required private forums;
- SMTP SPF/DKIM/DMARC configured;
- Telegram and S3 credentials stored in the deployment secret manager;
- privacy notice, simple confirmation wording, invoice details, tax handling and retention choices reviewed by the owner for the operating jurisdiction;
- Owner default credentials replaced and staff access reviewed;
- accessibility/browser smoke test completed on current Chrome, Safari, Firefox, Edge, iOS, and Android;
- restore drill and incident contact process completed.
