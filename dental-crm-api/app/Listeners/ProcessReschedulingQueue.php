<?php

namespace App\Listeners;

use App\Events\ScheduleChanged;
use App\Models\RescheduleCandidate;
use App\Notifications\RescheduleOptionsNotification;
use Illuminate\Support\Facades\Notification;

class ProcessReschedulingQueue
{
    public function handle(ScheduleChanged $event): void
    {
        $candidates = RescheduleCandidate::with(['doctor', 'patient'])
            ->whereIn('id', $event->candidateIds)
            ->where('status', 'pending')
            ->get();

        foreach ($candidates as $candidate) {
            $patient = $candidate->patient;

            if (! $patient) {
                $candidate->update(['status' => 'cancelled']);

                continue;
            }

            $doctorName = $candidate->doctor?->full_name ?? 'лікаря';
            $oldDateTime = optional($candidate->old_start_at)->format('Y-m-d H:i') ?? '';
            $suggestedSlots = $candidate->suggested_slots ?? [];

            Notification::send(
                $patient,
                new RescheduleOptionsNotification($doctorName, $oldDateTime, $suggestedSlots)
            );

            $candidate->update([
                'status' => 'notified',
                'notified_at' => now(),
            ]);
        }
    }
}
