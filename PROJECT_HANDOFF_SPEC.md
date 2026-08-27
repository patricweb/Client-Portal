# Ikira Client Portal — полное ТЗ и инструкция для продолжения разработки

## 1. Назначение документа

> Актуальное упрощение от 2026-08-28: обычный workflow больше не создаёт отдельные Proposal, MSA, SOW, Change Order, Acceptance, Handover и Care Agreement. Для новых проектов используются Project Confirmation, опциональный Change Confirmation, Delivery Confirmation и отдельные Invoices. Клиент подтверждает точную PDF-версию в портале; legacy-документы сохраняются только для истории и совместимости. Операционная инструкция: `docs/DOCUMENT_WORKFLOW.md`.

> Внутренний модуль команды от 2026-08-28: `Work Items` хранит задания в MySQL, связывает их с проектами и сотрудниками, синхронизирует статусы с закрытыми Telegram/Discord-каналами и полностью скрыт от клиентов. Инструкция: `docs/INTERNAL_WORK_ITEMS.md`.

Этот документ предназначен для разработчика, который продолжит работу над проектом **Ikira Client Portal** без доступа к предыдущей переписке.

В документе зафиксированы:

- назначение продукта;
- согласованные продуктовые решения;
- текущий технический стек;
- уже реализованные функции;
- структура проекта и данных;
- правила безопасности;
- последовательность следующих этапов;
- критерии готовности MVP;
- инструкция запуска на новом устройстве.

---

## 2. Общая идея продукта

**Ikira Client Portal** — внутренняя система компании **Ikira Company** и клиентский кабинет для сопровождения digital-проектов.

Типы проектов:

- Website;
- Landing Page;
- E-commerce;
- Web Application;
- Telegram Bot;
- Automation / Integration;
- Maintenance Only;
- Custom.

Система должна сопровождать клиента по полному workflow:

```text
Lead
→ Proposal
→ Client Portal Access
→ Brief
→ Contract + Scope of Work
→ Initial Invoice
→ Payment
→ Project Stages
→ Client Approvals
→ Final Invoice
→ Handover
→ Care & Support
→ Recurring Invoices
→ Support Requests
```

Главный принцип клиентской части: клиент должен за несколько секунд понять:

1. Что сейчас происходит с проектом?
2. Требуется ли от него действие?
3. Какой ближайший срок?
4. Сколько и за что он платит?
5. Что входит в Care & Support?

Главный принцип административной части: Owner должен видеть не общую статистику, а работу, которая требует внимания сегодня.

---

## 3. Рынок и локализация

Первая версия ориентирована на американский рынок:

- основной язык интерфейса — English;
- основная валюта — USD;
- формат дат — американский;
- timezone клиента хранится отдельно;
- интерфейс и документы должны быть написаны понятным деловым английским.

В будущем необходимо поддержать:

- Europe;
- Moldova;
- EUR и MDL;
- Romanian и другие языки;
- региональные форматы дат и документов.

Архитектура не должна жёстко зависеть от одной валюты или одного языка.

---

## 4. Формат продукта

На текущем этапе это не публичный SaaS.

Система используется только Ikira Company:

- одна организация-владелец;
- её сотрудники;
- её потенциальные и действующие клиенты;
- клиентские компании могут иметь несколько проектов;
- одна клиентская компания в будущем может иметь несколько Portal Users.

Мультитенантность для нескольких агентств пока не нужна.

---

## 5. Технический стек

- Laravel 13;
- PHP 8.4;
- Blade;
- Tailwind CSS 4;
- Alpine.js при необходимости;
- MySQL 8.0;
- Redis 7;
- Apache;
- Docker Compose;
- Laravel Queue;
- Laravel Scheduler;
- Vite;
- PHPUnit;
- Laravel Pint.

Планируемые интеграции:

- SMTP или transactional email provider;
- Telegram Bot API;
- S3-compatible object storage;
- PDF generation library.

Отдельный React/Vue frontend на этапе MVP не нужен.

---

## 6. Docker-архитектура

В `docker-compose.yml` определены сервисы:

### app

- PHP 8.4;
- Apache;
- Composer;
- Node.js 22;
- Laravel;
- порт портала: `8080`;
- порт Vite: `5173`.

### mysql

- MySQL 8.0;
- внутренний порт `3306`;
- внешний development-порт `3308`;
- persistent volume;
- healthcheck.

### redis

- Redis 7 Alpine;
- внешний development-порт `6380`;
- cache и queues;
- persistent volume;
- healthcheck.

### queue

Запускает:

```bash
php artisan queue:work --sleep=2 --tries=3 --timeout=120
```

В будущем используется для:

- email;
- Telegram notifications;
- PDF generation;
- тяжёлых фоновых операций.

### scheduler

Запускает:

```bash
php artisan schedule:work
```

В будущем используется для:

- overdue invoices;
- reminders;
- recurring invoice drafts;
- deadline notifications;
- Care & Support scheduling.

---

## 7. Запуск проекта на другом устройстве

### Требования

- Git;
- Docker Desktop;
- Docker Compose v2.

PHP, Composer, Node.js, MySQL и Redis на хосте не обязательны.

### Перенос проекта

Необходимо перенести весь репозиторий, а не только этот файл.

Рекомендуемый способ:

1. Создать private Git repository.
2. Отправить в него текущий проект.
3. На новом устройстве выполнить `git clone`.

Файл `.env` не должен попадать в Git. Его необходимо создать отдельно из `.env.example`.

### Первый запуск

Windows PowerShell:

```powershell
Copy-Item .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

Linux/macOS:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

Открыть:

```text
http://localhost:8080
```

Development Owner по умолчанию:

```text
Email: owner@ikira.company
Password: ChangeMe123!
```

Эти данные предназначены только для локальной разработки. Пароль и email необходимо изменить перед shared или production deployment.

### Полезные команды

```bash
docker compose ps
docker compose logs -f app queue scheduler
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app npm run build
docker compose down
```

Не использовать `docker compose down -v`, если данные MySQL необходимо сохранить.

---

## 8. Уже реализовано

### Этап 1 — Foundation

Реализовано:

- Laravel-приложение;
- Docker Compose;
- Apache/PHP container;
- MySQL;
- Redis;
- queue worker;
- scheduler;
- `.env.example`;
- healthchecks;
- автоматическая генерация `APP_KEY`;
- frontend build;
- роли Owner и Client;
- account statuses;
- login/logout;
- Forgot Password;
- Reset Password;
- временный пароль для клиента;
- обязательная смена временного пароля;
- role middleware;
- client data isolation;
- private attachment downloads.

Роли сейчас:

```text
owner
admin
project_manager
developer
support
accountant
client
```

Account statuses:

```text
invited
active
suspended
disabled
```

### Важная защита тестов

Контейнер передаёт приложению `DB_CONNECTION=mysql`, но тесты не должны использовать рабочую MySQL.

В `tests/TestCase.php` тестовая среда принудительно переключается на:

```text
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
```

Нельзя удалять эту защиту: без неё `RefreshDatabase` может очистить development MySQL.

### Этап 2 — Leads, Clients, Projects и Workflow

Реализовано:

- Leads list;
- создание и редактирование Lead;
- lead statuses;
- Companies;
- Company Contacts;
- primary contact;
- создание Client Portal access;
- одноразовый показ temporary credentials;
- список проектов клиента;
- Project types;
- Project statuses;
- Workflow Templates;
- Workflow Template Stages;
- копирование workflow в Project Stages;
- изменение статуса этапа Owner;
- автоматический пересчёт progress;
- стартовые workflow templates;
- стартовые Brief templates.

Lead statuses:

```text
new
contacted
discovery
proposal_sent
accepted
declined
archived
```

Project statuses:

```text
draft
awaiting_brief
awaiting_contract
awaiting_payment
scheduled
active
on_hold
completed
cancelled
maintenance
```

Project stage statuses:

```text
not_started
in_progress
approval_required
changes_requested
approved
completed
blocked
```

### Этап 3 — Client Portal

Реализовано:

- Client Home;
- Action Required block;
- отображение отсутствия необходимого действия;
- список клиентских проектов;
- Project Timeline;
- current stage;
- project progress;
- target completion date;
- staging URL support в модели;
- Brief form;
- Save Draft;
- required Brief fields;
- Submit Brief;
- блокировка submitted Brief;
- автоматический переход проекта после отправки Brief;
- приватная загрузка файлов;
- безопасное скачивание файлов;
- запрет клиенту видеть проект другой компании.

### Этапы 4–6 — Documents, Billing и Care & Support

Реализовано:

- Document Templates с подстановкой Company, Contact и Project data;
- Documents и immutable Document Versions;
- Proposal send/accept/request changes workflow;
- Contract PDF download и загрузка подписанного PDF;
- polymorphic Approvals для Documents и Project Stages;
- audit metadata решения: version, user, timestamp, IP и user agent;
- PDF generation через `dompdf/dompdf`;
- Payment Schedules с fixed/percentage milestones и trigger stage;
- Invoices с последовательными номерами, line items, discount, PDF и overdue status;
- отдельные Payments, partial payments и автоматический paid status;
- void вместо hard delete для Invoices;
- Client Billing с totals, paid, remaining и payment history;
- Care & Support Plans, included services и support-minute usage;
- ручные technical statuses, maintenance/backup activity;
- scheduler command `care:generate-invoices`, создающий draft recurring invoices;
- Policies и client isolation для Documents, Invoices и Care Plans.

### Этапы 7–11 — Requests, Notifications, Activity, Team и Production

Реализовано:

- Requests, public messages, internal notes, attachments и external communications;
- billing classification, Change Order draft и списание Care minutes при завершении;
- Portal, queued Email и Telegram delivery с журналом попыток и индивидуальными настройками;
- Activity Log с public/internal visibility, actor/IP/user agent и безопасной фильтрацией полей;
- обязательная причина и audit event для Owner approval override;
- роли Owner, Admin, Project Manager, Developer, Support, Accountant и Client;
- permission middleware, приглашение сотрудников, suspension и назначения по проектам;
- login rate limiting, security headers и пользовательские error pages;
- production Compose, healthcheck, queue/scheduler, backup/restore scripts и deployment runbook.

---

## 9. Текущая навигация

### Owner

```text
Today
Leads
Clients
Projects
Documents
Invoices
Care & Support
Requests
Activity
Team
```

### Client

```text
Home
Projects
Documents
Billing
Care & Support
Requests
Updates
```

Owner navigation фильтруется по permissions текущей роли.

План Owner navigation:

```text
Today
Leads
Clients
Projects
Approvals
Documents
Invoices
Care & Support
Requests
Team
Activity
Settings
```

План Client navigation:

```text
Home
Projects
Approvals
Documents
Billing
Care & Support
Requests
Profile
```

---

## 10. Текущие модели и таблицы

Реализованные основные таблицы:

```text
users
password_reset_tokens
sessions
cache
jobs
failed_jobs
leads
companies
company_contacts
workflow_templates
workflow_template_stages
projects
project_stages
brief_templates
brief_template_fields
project_briefs
brief_answers
attachments
document_templates
documents
document_versions
approvals
payment_schedules
payment_schedule_items
invoices
invoice_items
payments
care_plans
care_activities
```

### User

Основные поля:

- company_id;
- name;
- email;
- password;
- role;
- status;
- must_change_password;
- last_login_at.

### Company

- name;
- billing_name;
- email;
- phone;
- website;
- billing_address;
- timezone;
- currency;
- internal_notes.

### CompanyContact

- company_id;
- user_id;
- name;
- email;
- phone;
- job_title;
- is_primary.

### Project

- company_id;
- workflow_template_id;
- name;
- type;
- description;
- scope;
- exclusions;
- price;
- currency;
- status;
- progress;
- start_date;
- target_completion_date;
- staging_url;
- production_url;
- internal_notes.

### ProjectStage

- project_id;
- title;
- client_description;
- internal_description;
- position;
- status;
- start_date;
- due_date;
- requires_approval;
- approved_at.

### ProjectBrief

- project_id;
- brief_template_id;
- status;
- submitted_at;
- approved_at.

Brief statuses:

```text
draft
submitted
needs_clarification
approved
```

### Attachment

Используется polymorphic relation.

- uploaded_by;
- attachable_type;
- attachable_id;
- disk;
- path;
- original_name;
- mime_type;
- size.

Файлы должны храниться приватно. Нельзя отдавать прямой публичный URL без authorization check.

---

## 11. Этапы разработки

## Этап 4 — Documents и Approvals

Статус: реализован.

Это следующий обязательный этап.

### 4.1 Documents

Типы документов:

```text
Proposal
Scope of Work
Contract
Invoice
Change Order
Project Handover
Care & Support Agreement
Other
```

Требуемые функции:

- document templates;
- автоматическая подстановка Company, Contact и Project data;
- editable document content;
- document versions;
- preview;
- PDF generation;
- send to client;
- client view/download;
- upload signed Contract;
- immutable accepted/signed version;
- audit trail.

Document statuses:

```text
draft
sent
viewed
awaiting_approval
accepted
awaiting_signature
signed
expired
void
```

Принятый или подписанный документ нельзя редактировать. Изменение создаёт новую версию.

### 4.2 Proposal

Workflow:

```text
Draft
→ Preview
→ Send to Client
→ Client Accepts or Declines
→ Convert Lead to Client and Project
```

Proposal содержит:

- project summary;
- scope;
- exclusions;
- timeline;
- price;
- payment schedule;
- revision rounds;
- additional hourly rate;
- expiration date;
- suggested Care & Support.

### 4.3 Contract

Для MVP:

- Contract генерируется системой;
- клиент скачивает PDF;
- подписание происходит вне портала;
- Owner загружает signed PDF;
- статус меняется на `signed`.

Юридические шаблоны должны быть редактируемыми. Перед реальным использованием их должен проверить юрист с учётом американского законодательства.

### 4.4 Approvals

Согласовываемые объекты:

- Proposal;
- Project Stage;
- Design;
- Staging Version;
- Change Order;
- Handover.

Действия клиента:

```text
Approve
Request Changes
```

Сохранять:

- approvable_type;
- approvable_id;
- version;
- decision;
- comment;
- user_id;
- decided_at;
- IP и user agent при необходимости.

После approval связанная версия должна стать immutable.

---

## Этап 5 — Payment Schedule, Invoices и Payments

Статус: реализован.

### 5.1 Payment Schedule

Для каждого проекта используется индивидуальный график.

Примеры:

```text
50% — Deposit
50% — Before Launch
```

или:

```text
30% — Project Start
30% — Design Approval
30% — Development Complete
10% — Before Launch
```

Поддержать:

- fixed amount;
- percentage;
- due date;
- trigger stage;
- description;
- invoice creation status.

### 5.2 Invoice

Поля:

- invoice_number;
- client/company;
- project;
- issue_date;
- due_date;
- subtotal;
- discount;
- total;
- currency;
- payment instructions;
- public notes;
- internal notes.

Invoice statuses:

```text
draft
sent
viewed
partially_paid
paid
overdue
void
```

Функции:

- automatic sequential number;
- invoice items;
- PDF;
- send to client;
- mark as paid;
- partial payments;
- void instead of hard delete;
- overdue calculation by scheduler.

### 5.3 Payments

Payment хранится отдельно от Invoice:

- invoice_id;
- amount;
- paid_at;
- payment_method;
- transaction_reference;
- internal_note;
- recorded_by.

Оплата выполняется вне портала по реквизитам. Online payment integration пока не требуется.

### 5.4 Client Billing

Показывать:

- original project price;
- approved additions;
- total contract value;
- paid;
- remaining;
- next payment;
- invoice list;
- payment history;
- payment instructions.

---

## Этап 6 — Care & Support

Статус: реализован.

Общее название используется вместо только `Website Care`, поскольку система обслуживает websites, web apps и Telegram bots.

Типы планов:

```text
Website Care
Web App Maintenance
Bot Support
Hosting & Monitoring
Custom Support
```

Care Plan:

- name;
- description;
- monthly_price;
- currency;
- billing_frequency;
- included_services;
- included_support_minutes;
- additional_hourly_rate;
- start_date;
- next_billing_date;
- status.

Statuses:

```text
pending
active
paused
cancelled
expired
```

Client Care Dashboard:

- active plan;
- price;
- next billing date;
- included services;
- used minutes;
- last backup;
- last maintenance;
- SSL status;
- service status.

Для MVP technical statuses обновляются Owner вручную.

Scheduler должен создавать draft recurring invoice. Owner проверяет его перед отправкой.

---

## Этап 7 — Requests и минимальная переписка

Полноценный мессенджер не нужен. Основное общение остаётся в Instagram, email, WhatsApp и других внешних каналах.

В портале хранится только контекстная переписка по Request.

Request categories:

```text
bug
content_update
technical_issue
new_feature
billing
general_question
```

Client priority:

```text
normal
urgent
```

Statuses:

```text
new
in_progress
waiting_for_client
estimate_sent
completed
closed
```

Billing classification:

```text
warranty
included_in_care
complimentary
billable
```

Функции:

- create Request;
- project relation;
- attachments;
- client/Owner messages;
- status changes;
- internal priority;
- convert billable Request to Change Order;
- track included Care minutes.

Также необходима функция `Log external communication`:

```text
Client approved the homepage direction via Instagram on August 25.
```

---

## Этап 8 — Notifications

### Client channels

- Portal notification;
- Email.

### Owner channels

- Admin Portal notification;
- Email;
- Telegram Bot.

### Notification levels

```text
action_required
important_update
information
internal_alert
system_error
```

### Client events

- portal invitation;
- Proposal available;
- Brief clarification required;
- new document;
- Contract awaiting signature;
- Invoice created;
- payment due reminder;
- Invoice overdue;
- payment confirmed;
- Project Stage awaiting approval;
- requested changes completed;
- new Request message;
- project launched;
- recurring Care invoice.

### Owner events

- Brief submitted;
- Proposal accepted;
- stage approved;
- changes requested;
- client file uploaded;
- new Request;
- new Request message;
- payment proof uploaded;
- deadline approaching;
- Invoice overdue;
- recurring Invoice is due;
- email/Telegram delivery error.

### Delivery architecture

Событие должно создавать:

```text
Database Notification
Email Notification
Telegram Notification
```

Отправка через queue.

Хранить delivery log:

- channel;
- recipient;
- status;
- attempts;
- sent_at;
- failed_at;
- error_message.

Внешняя ошибка не должна отменять основное действие в портале.

Telegram Bot на MVP только отправляет Owner сообщения. Управление проектами командами Telegram пока не делать.

---

## Этап 9 — Activity Log

Хранить значимые действия:

- login;
- account access changes;
- Lead creation/update;
- Project creation/update;
- stage status changes;
- Brief submitted/approved;
- document sent/viewed/approved;
- Invoice created/sent/paid/voided;
- Payment recorded;
- file uploaded;
- Request created/updated;
- external communication note.

Activity может быть:

- public — доступна клиенту;
- internal — только Ikira.

Финансовые и юридические записи не удалять физически.

---

## Этап 10 — Team и Permissions

Будущие роли:

```text
owner
admin
project_manager
developer
support
accountant
client
```

Пример ограничений:

- Owner — полный доступ;
- Admin — почти полный операционный доступ;
- Project Manager — clients, projects, stages, approvals;
- Developer — только назначенные проекты и техническая работа;
- Support — Care & Support и Requests;
- Accountant — Invoices и Payments;
- Client — только собственная Company и её Projects.

Требуемые функции:

- invite team member;
- assign projects;
- role permissions;
- suspend access;
- individual notification settings;
- activity attribution.

---

## Этап 11 — Production Readiness

Перед реальным клиентом выполнить:

- responsive QA;
- browser QA;
- validation audit;
- authorization audit;
- file access audit;
- rate limiting;
- secure cookies;
- production `APP_ENV` и `APP_DEBUG=false`;
- HTTPS;
- SMTP configuration;
- Telegram Bot Token configuration;
- S3-compatible storage;
- database backups;
- backup restore test;
- deployment documentation;
- production queue supervisor;
- production scheduler cron/process;
- error monitoring;
- empty states;
- user-friendly error pages;
- owner review of business details and confirmation wording;
- accessibility review.

---

## 12. Главные бизнес-правила

1. Client видит только свою Company.
2. Client видит только Projects своей Company.
3. Один Client может иметь несколько Projects.
4. Одна Company может иметь несколько Contacts и Portal Users.
5. Один Project может иметь несколько Invoices.
6. Один Invoice может иметь несколько Payments.
7. Accepted или Signed document нельзя редактировать.
8. Изменение документа создаёт новую version.
9. Stage с `requires_approval=true` нельзя автоматически завершить без Approval.
10. Owner override должен требовать reason и попадать в Activity Log.
11. Дополнительная работа оформляется через Change Order.
12. Change Order не изменяет исходную цену незаметно.
13. Invoice и Payment нельзя hard-delete; использовать `void` или archive.
14. Файлы всегда проходят authorization check.
15. Пароль клиента нельзя показать Owner после создания.
16. Temporary password показывается только один раз.
17. Telegram Bot Token и другие secrets должны храниться зашифрованно.
18. Нельзя сохранять пароли, SSH keys и другие secrets в PDF Documents.
19. Ошибка email или Telegram не должна блокировать действие в приложении.
20. Client-facing text должен быть понятен нетехническому пользователю.

---

## 13. UI/UX принципы

### Client Home

Порядок информации:

1. Action Required;
2. Current Project;
3. Current Stage;
4. Target Completion;
5. Next Payment;
6. Open Requests;
7. Recent Updates.

Если действие не требуется:

```text
No action is required from you right now.
We are currently working on Development.
```

### Owner Today

Показывать:

- waiting for Owner;
- waiting for Client;
- overdue client actions;
- deadlines;
- unpaid invoices;
- new Requests;
- recurring invoices due;
- recent activity.

Не перегружать стартовую страницу декоративной аналитикой.

### Terminology

Использовать:

- `Requests`, а не техническое `Tickets`;
- `Care & Support`, а не только `Website Care`;
- `Approval Required`;
- `Payment Schedule`;
- `Project Updates`;
- `Company` и `Contacts`.

---

## 14. Безопасность

Обязательные требования:

- server-side validation;
- CSRF protection;
- authorization middleware/policies;
- role checks;
- company/project ownership checks;
- private file storage;
- secure password hashing;
- password reset tokens;
- login rate limiting;
- session regeneration after login;
- logout session invalidation;
- encrypted third-party tokens;
- audit log;
- no secrets in logs;
- no secrets in PDFs;
- no hard-delete for legal/financial data.

Для новых сущностей предпочтительно использовать Policies вместо дублирования authorization logic в контроллерах.

---

## 15. Тестирование

Текущий набор:

- 47 tests;
- 260 assertions;
- Owner login;
- password reset request;
- temporary password enforcement;
- client project isolation;
- Brief save and submit;
- Company + Portal User creation;
- Project creation from Workflow and Brief templates.

Перед завершением каждого следующего этапа добавить tests для:

- authorization;
- happy path;
- validation;
- state transitions;
- immutable records;
- client isolation;
- queues/notifications;
- file access.

Команды проверки:

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan view:cache
docker compose exec app composer validate --no-check-publish
docker compose exec app npm run build
```

После тестов development MySQL должна сохранять Owner и реальные local data.

---

## 16. Definition of Done для MVP

MVP готов, когда реального клиента можно провести по цепочке:

```text
Lead created
→ Proposal sent and accepted
→ Client account issued
→ Temporary password changed
→ Brief submitted and approved
→ Contract uploaded as signed
→ Initial Invoice sent
→ Payment recorded
→ Project stages updated
→ Client approval recorded
→ Final Invoice paid
→ Handover accepted
→ Care & Support activated
→ Monthly Invoice created
→ Client Request completed
```

При этом:

- клиент получает Portal + Email notifications;
- Owner получает Portal + Email + Telegram notifications;
- все важные действия попадают в Activity Log;
- клиент не может получить доступ к чужим данным;
- документы и финансовые данные имеют корректную историю;
- контейнеры стабильно запускаются на чистом устройстве;
- backup можно восстановить.

---

## 17. Дальнейшее развитие после MVP

Базовые этапы 1–11 завершены. Перед реальным production launch выполнить внешний deployment checklist из `DEPLOYMENT.md`: подключить домен/TLS, реальные SMTP/Telegram/S3 credentials, проверить собственные реквизиты и сделать пробное восстановление backup в отдельной тестовой среде.

После запуска можно планировать online payments, electronic signature и automatic monitoring как отдельные интеграции.

---

## 18. Важные файлы проекта

```text
Dockerfile
docker-compose.yml
.env.example
README.md
PROJECT_HANDOFF_SPEC.md
docker/entrypoint.sh
docker/apache-vhost.conf
docker/php.ini
routes/web.php
app/Enums/
app/Models/
app/Http/Controllers/
app/Http/Middleware/
database/migrations/
database/seeders/DatabaseSeeder.php
resources/views/
tests/TestCase.php
tests/Feature/PortalWorkflowTest.php
tests/Feature/StagesFourToSixTest.php
tests/Feature/StagesSevenToElevenTest.php
DEPLOYMENT.md
tests/Feature/StagesFourToSixTest.php
```

Перед изменением структуры необходимо сначала изучить существующие migrations, relationships, middleware и tests.
