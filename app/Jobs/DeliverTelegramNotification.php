<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverTelegramNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::findOrFail($this->deliveryId);
        $delivery->increment('attempts');
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.owner_chat_id');
        if (blank($token) || blank($chatId)) {
            $delivery->update(['status' => 'skipped', 'error_message' => 'Telegram is not configured.']);

            return;
        }
        try {
            $payload = $delivery->payload;
            Http::timeout(10)->retry(2, 250)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId, 'text' => $payload['title']."\n\n".$payload['message'],
            ])->throw();
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'error_message' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => str($exception->getMessage())->limit(2000)]);
            throw $exception;
        }
    }
}
