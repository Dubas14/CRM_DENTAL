<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS notification', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
