<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Models\WaitlistOffer;
use App\Notifications\WaitlistOfferNotification;
use App\Services\Calendar\WaitlistService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendWaitlistOffers
{
    public function handle(AppointmentCancelled $event): void
    {
        $appointment = $event->appointment->loadMissing(['doctor', 'procedure']);

        $service = new WaitlistService;
        $candidates = $service->matchCandidates(
            $appointment->clinic_id,
            $appointment->doctor_id,
            $appointment->procedure_id,
            $appointment->start_at?->copy()->startOfDay()
        );

        if ($candidates->isEmpty()) {
            return;
        }

        $doctorName = $appointment->doctor?->full_name ?? 'лікаря';
        $dateTime = $appointment->start_at?->format('Y-m-d H:i') ?? '';

        foreach ($candidates as $entry) {
            if (! $entry->patient) {
                continue;
            }

            $token = (string) Str::uuid();
            $offer = WaitlistOffer::create([
                'appointment_id' => $appointment->id,
                'waitlist_entry_id' => $entry->id,
                'token' => $token,
                'expires_at' => now()->addHours(2),
            ]);

            $claimUrl = rtrim(config('app.url'), '/').'/booking/claim/'.$offer->token;

            Notification::send(
                $entry->patient,
                new WaitlistOfferNotification($doctorName, $dateTime, $claimUrl)
            );
        }
    }
}
