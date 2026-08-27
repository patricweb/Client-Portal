# Internal Work Items

`Work Items` is a private Ikira team module. Client accounts have no route, permission or navigation entry for it.

## What it provides

- project-linked or general internal assignments;
- Web, Telegram Bot, Python, Design, Development, 3D and Other areas;
- assignee, priority, due date and internal price;
- New, Assigned, In Progress, Review, Done and Cancelled statuses;
- search, filters and status statistics;
- internal-only audit history;
- queued synchronization to a private Telegram topic and Discord forum;
- Telegram buttons that update the same MySQL record;
- archiving without deleting the operational history.

MySQL is the only source of truth. Do not run the old Python dispatcher in parallel and do not restore `orders.json` as a live database.

## Access rules

- Owner and Admin can see every work item.
- Project Manager, Developer and Support can use the module but only see items assigned to them, created by them, or attached to a project they are assigned to.
- Developer and Support cannot view or overwrite the internal price.
- Client accounts cannot access any Work Item route.

## Required credential rotation

The old `D:\Ikira Bot\bot.py` contains Telegram and Discord tokens in source code. Treat both tokens as compromised:

1. Revoke the old Telegram token through BotFather and create a new token.
2. Reset the Discord bot token in the Discord Developer Portal.
3. Never paste either token into Git-tracked files.
4. Put the replacement values only in the server `.env`.

## Configuration

```dotenv
TELEGRAM_BOT_TOKEN=NEW_ROTATED_TOKEN
TELEGRAM_OWNER_CHAT_ID=YOUR_PRIVATE_OWNER_CHAT_ID
TELEGRAM_WORK_CHAT_ID=-1003665841029
TELEGRAM_WORK_TOPIC_ID=2
TELEGRAM_WEBHOOK_SECRET=LONG_RANDOM_LETTERS_NUMBERS_UNDERSCORES_OR_DASHES
TELEGRAM_ALLOWED_USER_IDS=YOUR_TELEGRAM_USER_ID,SECOND_TEAM_USER_ID

DISCORD_BOT_TOKEN=NEW_ROTATED_TOKEN
DISCORD_FORUM_WEB=1493319853434732695
DISCORD_FORUM_TELEGRAM_BOT=1493321440827539676
DISCORD_FORUM_PYTHON=1493321440827539676
DISCORD_FORUM_DESIGN=1493321706041774190
DISCORD_FORUM_DEVELOPMENT=1518381323092361378
DISCORD_FORUM_3D=1493321765055889448
DISCORD_FORUM_OTHER=1493321440827539676
WORK_ITEM_CHANNELS_INCLUDE_PRICE=false
```

Keep `WORK_ITEM_CHANNELS_INCLUDE_PRICE=false` unless every member of the connected Telegram topic and Discord forums is authorized to see internal prices.

The Telegram webhook requires a public HTTPS `APP_URL`. After deployment and after changing the bot token or callback URL, run:

```powershell
docker compose exec app php artisan config:clear
docker compose exec app php artisan ikira:telegram-webhook
docker compose restart queue
```

To remove the webhook:

```powershell
docker compose exec app php artisan ikira:telegram-webhook --remove
```

The webhook accepts only Telegram callback updates with the configured secret header, private work chat ID and optional allow-list of team Telegram user IDs.

## Operating flow

1. Open `Work Items` in the staff navigation.
2. Create an assignment and optionally link it to a project.
3. The queue publishes it to configured internal channels.
4. Change status in the portal or with a Telegram button.
5. The queue updates Telegram and Discord from the current MySQL state.
6. Archive completed historical items instead of deleting them.

If a channel fails, the work item stays saved. Its page displays the error and offers `Sync channels` for a retry.
