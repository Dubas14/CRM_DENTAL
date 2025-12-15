<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;

class RescheduleService
{
    public function buildRescheduleQueue(Doctor $doctor, Carbon $from, Carbon $to): array
    {
        $appointments = Appointment::with(['procedure', 'room', 'equipment'])
            ->where('doctor_id', $doctor->id)
            ->whereBetween('start_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('start_at')
            ->get();

        $availabilityService = new AvailabilityService();
        $queue = [];

        foreach ($appointments as $appointment) {
            $procedure = $appointment->procedure;
            $duration = $procedure?->duration_minutes
                ?? ($appointment->end_at ? $appointment->start_at->diffInMinutes($appointment->end_at) : 0);

            if ($duration <= 0) {
                continue;
            }

            $searchFrom = $to->copy()->startOfDay();
            $suggestedSlots = $availabilityService->suggestSlots(
                $doctor,
                $searchFrom,
                $duration,
                $appointment->room,
                $appointment->equipment ?? $procedure?->equipment,
                5
            );

            $queue[] = [
                'appointment_id' => $appointment->id,
                'old_start_at' => $appointment->start_at,
                'suggested_slots' => $suggestedSlots,
            ];
        }

        return $queue;
    }
}
