<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        $phoneMasked = strlen($phone) > 4
            ? substr($phone, 0, 2).str_repeat('*', strlen($phone) - 4).substr($phone, -2)
            : '****';

        Log::info('SMS notification', [
            'phone_masked' => $phoneMasked,
            'message_length' => strlen($message),
        ]);
    }
}
