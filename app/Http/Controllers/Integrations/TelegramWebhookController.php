<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWorkItemChannels;
use App\Models\WorkItem;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');
        abort_if($secret === '', 404);
        abort_unless(hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $callback = $request->input('callback_query');
        if (! is_array($callback) || ! preg_match('/^work:(\d+):(in_progress|review|done|cancelled)$/', (string) data_get($callback, 'data'), $matches)) {
            return response()->json(['ok' => true]);
        }

        $this->authorizeTelegramUser((string) data_get($callback, 'from.id'));
        $configuredChat = (string) config('services.telegram.work_chat_id');
        if ($configuredChat !== '') {
            abort_unless(hash_equals($configuredChat, (string) data_get($callback, 'message.chat.id')), 403);
        }

        $workItem = WorkItem::whereNull('archived_at')->find($matches[1]);
        if (! $workItem) {
            $this->answer((string) data_get($callback, 'id'), 'Work item is unavailable.', true);

            return response()->json(['ok' => true]);
        }

        $status = $matches[2];
        if ($workItem->status !== $status) {
            $workItem->update([
                'status' => $status,
                'started_at' => $status === 'in_progress' ? ($workItem->started_at ?? now()) : $workItem->started_at,
                'completed_at' => $status === 'done' ? now() : null,
                'channel_sync_status' => 'pending',
            ]);
            app(ActivityLogger::class)->log(
                'work_item.telegram_status_changed',
                'Work item status changed from Telegram.',
                $workItem,
                'internal',
                ['status' => $status, 'telegram_user_id' => (string) data_get($callback, 'from.id')],
                $workItem->company_id,
                $workItem->project_id,
            );
            SyncWorkItemChannels::dispatch($workItem->id)->afterCommit();
        }

        $this->answer((string) data_get($callback, 'id'), 'Status: '.WorkItem::STATUSES[$status]);

        return response()->json(['ok' => true]);
    }

    private function authorizeTelegramUser(string $telegramUserId): void
    {
        $allowed = config('services.telegram.allowed_user_ids', []);
        if ($allowed !== []) {
            abort_unless(in_array($telegramUserId, $allowed, true), 403);
        }
    }

    private function answer(string $callbackId, string $text, bool $alert = false): void
    {
        if ($callbackId === '' || blank(config('services.telegram.bot_token'))) {
            return;
        }
        $token = config('services.telegram.bot_token');
        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $alert,
        ]);
    }
}
