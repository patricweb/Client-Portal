# Простые подтверждения в портале

Портал не изображает юридическую фирму и не обещает квалифицированную электронную подпись. Его задача проще: показать клиенту понятные условия, сохранить неизменяемую PDF-версию и записать явное решение клиента.

## Какие записи используются

| Запись | Когда нужна |
| --- | --- |
| Project Confirmation | Один раз перед началом проекта: объём, исключения, цена, оплата и срок |
| Change Confirmation | Только если клиент согласует дополнительную или изменённую работу |
| Delivery Confirmation | После передачи результата: что доставлено, что проверено и какие мелкие пункты остались |
| Invoice | Отдельный финансовый документ для запроса оплаты |

Старые Proposal, MSA, SOW, Change Order и другие документы остаются доступными в истории, но интерфейс больше не предлагает создавать их в обычном workflow.

## Рабочая последовательность

1. В **Provider settings** заполнить настоящее имя исполнителя, адрес, email и проверенные банковские реквизиты.
2. Создать клиента и проект. Проверить billing name, billing address, контакт, описание, стоимость и сроки.
3. Открыть **Confirmations → New confirmation** и создать Project Confirmation.
4. Заполнить все выделенные поля, сохранить draft и скачать PDF для проверки.
5. Нажать **Send to client**. Клиент читает точную PDF-версию, ставит отдельную отметку о намерении и выбирает **Confirm this version** или **Request changes**.
6. Для дополнительной работы создать Change Confirmation, привязанный к принятому Project Confirmation. Поле цены означает новый полный итог проекта.
7. После передачи создать Delivery Confirmation. Для мелких оставшихся пунктов записать точные действия и даты.
8. Счета создавать в Billing. Advance и Final invoice привязываются к принятому Project Confirmation; Final также требует принятого Delivery Confirmation.

## Что сохраняется при решении клиента

- номер и точная версия;
- замороженный PDF и его SHA-256;
- аккаунт клиента;
- решение и комментарий;
- дата и время;
- IP-адрес и user agent.

Оплата, просмотр страницы или молчание не считаются подтверждением. Новый draft не меняет ранее выданный PDF и не скрывает старое решение.

## Стиль PDF

Все новые подтверждения и счета создаются в чёрно-белом виде: чёрный текст, белый фон, чёрные линии и таблицы. Цвет интерфейса портала не переносится в PDF. Это упрощает печать и исключает зависимость смысла от цвета.

## Обновление проекта

После `git pull`:

```bash
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app npm install --ignore-scripts
docker compose exec app php artisan migrate --force
docker compose exec app npm run build
docker compose restart queue scheduler
```

Не использовать `migrate:fresh` на рабочей базе.

## Backup и восстановление

Полный backup содержит MySQL и `storage/app`, включая PDF, подписанные legacy-файлы и вложения:

```powershell
.\docker\backup.ps1
.\docker\restore.ps1 -BackupFile .\backups\ikira-full-YYYYMMDD-HHMMSS.zip -ConfirmRestore
```

Хранить зашифрованную копию вне сервера и выполнить пробное восстановление до запуска. Не добавлять `.env`, токены, банковские данные и клиентские документы в Git.

## Основа электронного подтверждения

Американский E-SIGN Act не позволяет отклонить запись только потому, что она электронная, и уделяет внимание доступности и точному воспроизведению сохранённой записи. FTC также указывает на намерение человека применить электронный процесс к конкретной записи. Поэтому интерфейс требует отдельную отметку намерения и сохраняет воспроизводимую версию.

Официальные источники:

- https://www.govinfo.gov/app/details/PLAW-106publ229
- https://www.ftc.gov/legal-library/browse/advisory-opinions/advisory-opinion-zalenski-05-24-01

Это операционная запись договорённостей, а не обещание применимости ко всем отраслям, штатам и обязательным государственным формам.

## Проверки разработчика

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app composer validate --no-check-publish
docker compose exec app npm run build
```

Для экспорта демонстрационных PDF:

```bash
docker compose exec -e EXPORT_DOCUMENT_PDF_QA=1 app php artisan test --filter=test_all_pack_pdfs_render
```
