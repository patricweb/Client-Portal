<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfigureTelegramWebhook extends Command
{
    protected $signature = 'ikira:telegram-webhook {--remove : Remove the configured webhook}';

    protected $description = 'Configure Telegram callbacks for internal work item buttons';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $method = $this->option('remove') ? 'deleteWebhook' : 'setWebhook';
        $webhookUrl = route('integrations.telegram.webhook');
        $webhookSecret = (string) config('services.telegram.webhook_secret');
        $payload = $this->option('remove') ? ['drop_pending_updates' => false] : [
            'url' => $webhookUrl,
            'secret_token' => $webhookSecret,
            'allowed_updates' => ['callback_query'],
            'drop_pending_updates' => false,
        ];
        if (! $this->option('remove') && ! preg_match('/^[A-Za-z0-9_-]{16,256}$/', $webhookSecret)) {
            $this->error('TELEGRAM_WEBHOOK_SECRET must be 16-256 letters, numbers, underscores or dashes.');

            return self::FAILURE;
        }
        if (! $this->option('remove') && ! str_starts_with($webhookUrl, 'https://')) {
            $this->error('APP_URL must be a public HTTPS URL before configuring the Telegram webhook.');

            return self::FAILURE;
        }

        Http::timeout(15)->post("https://api.telegram.org/bot{$token}/{$method}", $payload)->throw();
        $this->info($this->option('remove') ? 'Telegram webhook removed.' : 'Telegram webhook configured.');

        return self::SUCCESS;
    }
}
