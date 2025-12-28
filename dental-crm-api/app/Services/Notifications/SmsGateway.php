<?php

namespace App\Services\Notifications;

interface SmsGateway
{
    public function send(string $phone, string $message): void;
}
