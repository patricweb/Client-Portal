<?php

namespace App\Services;

use App\Jobs\DeliverEmailNotification;
use App\Jobs\DeliverTelegramNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function send(User|Collection|array $recipients, string $event, string $level, string $title, string $message, ?string $url = null, bool $telegramForOwners = true): void
    {
        $users = $recipients instanceof User ? collect([$recipients]) : collect($recipients);
        foreach ($users->filter() as $user) {
            $payload = compact('event', 'level', 'title', 'message', 'url');
            $preferences = $user->notification_preferences ?? ['portal' => true, 'email' => true, 'telegram' => false];
            if ($preferences['portal'] ?? true) {
                $user->notify(new PortalNotification($payload));
                NotificationDelivery::create([
                    'user_id' => $user->id, 'event' => $event, 'level' => $level, 'channel' => 'portal',
                    'recipient' => (string) $user->id, 'status' => 'sent', 'attempts' => 1, 'sent_at' => now(), 'payload' => $payload,
                ]);
            }
            if (($preferences['email'] ?? true) && filled($user->email)) {
                $delivery = NotificationDelivery::create([
                    'user_id' => $user->id, 'event' => $event, 'level' => $level, 'channel' => 'email',
                    'recipient' => $user->email, 'status' => 'pending', 'payload' => $payload,
                ]);
                DeliverEmailNotification::dispatch($delivery->id);
            }
            if ($telegramForOwners && $user->isStaff() && ($preferences['telegram'] ?? $user->isOwner())) {
                $delivery = NotificationDelivery::create([
                    'user_id' => $user->id, 'event' => $event, 'level' => $level, 'channel' => 'telegram',
                    'recipient' => (string) config('services.telegram.owner_chat_id', 'owner'), 'status' => 'pending', 'payload' => $payload,
                ]);
                DeliverTelegramNotification::dispatch($delivery->id);
            }
        }
    }
}
