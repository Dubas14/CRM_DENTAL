<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RescheduleOptionsNotification extends Notification
{
    public function __construct(
        private string $doctorName,
        private string $oldDateTime,
        private array $suggestedSlots
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
        $message = (new MailMessage)
            ->subject('Потрібне перенесення запису')
            ->greeting('Доброго дня!')
            ->line("Ваш запис до лікаря {$this->doctorName} потребує перенесення.")
            ->line("Попередній час: {$this->oldDateTime}.");

        if (! empty($this->suggestedSlots)) {
            $message->line('Можливі варіанти:');
            foreach ($this->suggestedSlots as $slot) {
                $message->line("- {$slot['date']} {$slot['start']}–{$slot['end']}");
            }
        }

        return $message->line('Будь ласка, звʼяжіться з адміністратором для підтвердження.');
    }

    public function toSms(mixed $notifiable): array
    {
        $slots = array_map(
            fn ($slot) => "{$slot['date']} {$slot['start']}–{$slot['end']}",
            $this->suggestedSlots
        );
        $slotsText = $slots ? ' Варіанти: '.implode(', ', $slots) : '';

        return [
            'phone' => $notifiable->phone ?? null,
            'message' => "Потрібно перенести запис до {$this->doctorName} ({$this->oldDateTime}).{$slotsText}",
        ];
    }
}
