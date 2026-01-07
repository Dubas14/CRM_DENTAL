<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistOfferNotification extends Notification
{
    public function __construct(
        private string $doctorName,
        private string $dateTime,
        private string $claimUrl
    ) {}

    public function via(mixed $notifiable): array
    {
        $channels = [];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        if (! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Зʼявився вільний слот для запису')
            ->greeting('Доброго дня!')
            ->line("Є вільний слот до лікаря {$this->doctorName} на {$this->dateTime}.")
            ->action('Підтвердити запис', $this->claimUrl)
            ->line('Слот буде заброньовано за першим підтвердженням.');
    }

    public function toSms(mixed $notifiable): array
    {
        return [
            'phone' => $notifiable->phone ?? null,
            'message' => "Вільний слот до {$this->doctorName} на {$this->dateTime}. Підтвердити: {$this->claimUrl}",
        ];
    }
}
