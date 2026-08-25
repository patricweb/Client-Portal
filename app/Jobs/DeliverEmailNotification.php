<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverEmailNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::findOrFail($this->deliveryId);
        $delivery->increment('attempts');
        try {
            $payload = $delivery->payload;
            Mail::raw($payload['message'], function ($mail) use ($delivery, $payload) {
                $mail->to($delivery->recipient)->subject($payload['title']);
            });
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'error_message' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => str($exception->getMessage())->limit(2000)]);
            throw $exception;
        }
    }
}
