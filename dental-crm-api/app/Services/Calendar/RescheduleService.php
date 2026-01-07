<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\RescheduleCandidate;
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

        $availabilityService = new AvailabilityService;
        $queue = [];

        foreach ($appointments as $appointment) {
            $procedure = $appointment->procedure;
            $duration = $appointment->end_at
                ? $appointment->start_at->diffInMinutes($appointment->end_at)
                : 0;

            if ($duration <= 0) {
                $duration = $availabilityService->resolveProcedureDuration(
                    $doctor,
                    $procedure,
                    0
                );
            }

            if ($duration <= 0) {
                continue;
            }

            $searchFrom = $to->copy()->startOfDay();
            $suggestedSlots = $availabilityService->suggestSlots(
                $doctor,
                $searchFrom,
                $duration,
                $procedure,
                $appointment->room,
                $appointment->equipment ?? $procedure?->equipment,
                5
            );

            $queue[] = [
                'appointment_id' => $appointment->id,
                'old_start_at' => $appointment->start_at,
                'old_end_at' => $appointment->end_at,
                'patient_id' => $appointment->patient_id,
                'suggested_slots' => $suggestedSlots,
            ];
        }

        return $queue;
    }

    public function storeRescheduleCandidates(Doctor $doctor, array $queue): array
    {
        $createdIds = [];

        foreach ($queue as $item) {
            $candidate = RescheduleCandidate::create([
                'clinic_id' => $doctor->clinic_id,
                'doctor_id' => $doctor->id,
                'appointment_id' => $item['appointment_id'],
                'patient_id' => $item['patient_id'] ?? null,
                'old_start_at' => $item['old_start_at'],
                'old_end_at' => $item['old_end_at'] ?? null,
                'suggested_slots' => $item['suggested_slots'] ?? [],
                'status' => 'pending',
            ]);

            $createdIds[] = $candidate->id;
        }

        return $createdIds;
    }
}
