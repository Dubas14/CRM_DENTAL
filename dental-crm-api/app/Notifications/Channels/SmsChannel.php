<?php

namespace App\Notifications\Channels;

use App\Services\Notifications\SmsGateway;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsGateway $gateway) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $payload = $notification->toSms($notifiable);
        $phone = $payload['phone'] ?? null;
        $message = $payload['message'] ?? null;

        if (! $phone || ! $message) {
            return;
        }

        $this->gateway->send($phone, $message);
    }
}
