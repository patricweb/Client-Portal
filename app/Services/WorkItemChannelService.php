<?php

namespace App\Services;

use App\Models\WorkItem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WorkItemChannelService
{
    public function sync(WorkItem $workItem): void
    {
        $workItem->forceFill(['channel_sync_status' => 'syncing', 'channel_sync_error' => null])->saveQuietly();
        $configured = false;

        try {
            if (filled(config('services.telegram.bot_token')) && filled(config('services.telegram.work_chat_id'))) {
                $configured = true;
                $this->syncTelegram($workItem);
            }

            $forumId = $this->discordForumId($workItem->discipline);
            if (filled(config('services.discord.bot_token')) && filled($forumId)) {
                $configured = true;
                $this->syncDiscord($workItem, $forumId);
            }

            $workItem->forceFill([
                'channel_sync_status' => $configured ? 'synced' : 'not_configured',
                'channel_sync_error' => null,
                'last_channel_sync_at' => now(),
            ])->saveQuietly();
        } catch (\Throwable $exception) {
            $workItem->forceFill([
                'channel_sync_status' => 'failed',
                'channel_sync_error' => str($exception->getMessage())->limit(2000),
            ])->saveQuietly();

            throw $exception;
        }
    }

    private function syncTelegram(WorkItem $workItem): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.work_chat_id');
        $payload = [
            'chat_id' => $chatId,
            'text' => $this->telegramText($workItem),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => ['inline_keyboard' => $this->telegramKeyboard($workItem)],
        ];
        if (filled(config('services.telegram.work_topic_id'))) {
            $payload['message_thread_id'] = (int) config('services.telegram.work_topic_id');
        }

        $method = $workItem->telegram_message_id ? 'editMessageText' : 'sendMessage';
        if ($workItem->telegram_message_id) {
            $payload['message_id'] = (int) $workItem->telegram_message_id;
        }

        $response = Http::timeout(15)->retry(2, 300, null, false)->post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        if ($workItem->telegram_message_id && $response->failed()) {
            $description = (string) $response->json('description', '');
            if (str_contains($description, 'message is not modified')) {
                return;
            }
            if (str_contains($description, 'message to edit not found')) {
                $workItem->forceFill(['telegram_message_id' => null])->saveQuietly();
                $this->syncTelegram($workItem);

                return;
            }
        }
        $result = $response->throw()->json();
        if (! ($result['ok'] ?? false)) {
            throw new RuntimeException('Telegram did not accept the work item update.');
        }

        $messageId = data_get($result, 'result.message_id', $workItem->telegram_message_id);
        $workItem->forceFill(['telegram_chat_id' => $chatId, 'telegram_message_id' => (string) $messageId])->saveQuietly();
    }

    private function syncDiscord(WorkItem $workItem, string $forumId): void
    {
        $request = $this->discordRequest();
        $name = str($workItem->title.' — '.WorkItem::STATUSES[$workItem->status])->limit(95)->toString();
        $content = $this->discordText($workItem);

        if (! $workItem->discord_thread_id) {
            $result = $request->post("https://discord.com/api/v10/channels/{$forumId}/threads", [
                'name' => $name,
                'auto_archive_duration' => 1440,
                'message' => ['content' => $content, 'allowed_mentions' => ['parse' => []]],
            ])->throw()->json();
            $threadId = (string) data_get($result, 'id');
            if ($threadId === '') {
                throw new RuntimeException('Discord did not return a thread ID.');
            }
            $messageId = (string) data_get($result, 'message.id', $threadId);
            $workItem->forceFill([
                'discord_forum_id' => $forumId,
                'discord_thread_id' => $threadId,
                'discord_message_id' => $messageId,
            ])->saveQuietly();

            return;
        }

        $request->patch("https://discord.com/api/v10/channels/{$workItem->discord_thread_id}", ['name' => $name])->throw();
        if ($workItem->discord_message_id) {
            $request->patch("https://discord.com/api/v10/channels/{$workItem->discord_thread_id}/messages/{$workItem->discord_message_id}", [
                'content' => $content,
                'allowed_mentions' => ['parse' => []],
            ])->throw();
        }
    }

    private function discordRequest(): PendingRequest
    {
        return Http::withToken((string) config('services.discord.bot_token'), 'Bot')->acceptJson()->timeout(15)->retry(2, 500);
    }

    private function telegramText(WorkItem $workItem): string
    {
        $lines = [
            '<b>'.e($workItem->title).'</b>',
            'Status: <b>'.e(WorkItem::STATUSES[$workItem->status]).'</b>',
            'Area: '.e(WorkItem::DISCIPLINES[$workItem->discipline]),
            'Priority: '.e(WorkItem::PRIORITIES[$workItem->priority]),
        ];
        if ($workItem->project) {
            $lines[] = 'Project: '.e($workItem->project->name);
        }
        if ($workItem->assignee) {
            $lines[] = 'Assigned to: '.e($workItem->assignee->name);
        }
        if ($workItem->due_date) {
            $lines[] = 'Due: '.$workItem->due_date->format('Y-m-d');
        }
        if (config('services.work_item_channels.include_price') && $workItem->price !== null) {
            $lines[] = 'Internal price: '.e($workItem->currency).' '.number_format((float) $workItem->price, 2);
        }
        if (filled($workItem->description)) {
            $lines[] = "\n".e(str($workItem->description)->limit(1500));
        }
        $lines[] = "\n".'Portal: '.e(route('owner.work-items.edit', $workItem));
        if ($workItem->archived_at) {
            array_unshift($lines, '<b>ARCHIVED</b>');
        }

        return implode("\n", $lines);
    }

    private function discordText(WorkItem $workItem): string
    {
        $lines = [
            '**Status:** '.WorkItem::STATUSES[$workItem->status],
            '**Area:** '.WorkItem::DISCIPLINES[$workItem->discipline],
            '**Priority:** '.WorkItem::PRIORITIES[$workItem->priority],
        ];
        if ($workItem->project) {
            $lines[] = '**Project:** '.$workItem->project->name;
        }
        if ($workItem->assignee) {
            $lines[] = '**Assigned to:** '.$workItem->assignee->name;
        }
        if ($workItem->due_date) {
            $lines[] = '**Due:** '.$workItem->due_date->format('Y-m-d');
        }
        if (config('services.work_item_channels.include_price') && $workItem->price !== null) {
            $lines[] = '**Internal price:** '.$workItem->currency.' '.number_format((float) $workItem->price, 2);
        }
        if (filled($workItem->description)) {
            $lines[] = "\n".str($workItem->description)->limit(1500);
        }
        $lines[] = "\n".'Portal: '.route('owner.work-items.edit', $workItem);
        if ($workItem->archived_at) {
            array_unshift($lines, '**ARCHIVED**');
        }

        return implode("\n", $lines);
    }

    private function telegramKeyboard(WorkItem $workItem): array
    {
        if ($workItem->archived_at) {
            return [];
        }

        return collect(['in_progress' => 'In progress', 'review' => 'Review', 'done' => 'Done', 'cancelled' => 'Cancel'])
            ->map(fn (string $label, string $status) => [[
                'text' => ($workItem->status === $status ? '✓ ' : '').$label,
                'callback_data' => "work:{$workItem->id}:{$status}",
            ]])->values()->all();
    }

    private function discordForumId(string $discipline): ?string
    {
        return config("services.discord.work_forums.{$discipline}")
            ?: config('services.discord.work_forums.other');
    }
}
