<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Procedure;
use App\Models\Room;
use Carbon\Carbon;

class ConflictChecker
{
    public function evaluate(
        Doctor $doctor,
        Carbon $date,
        Carbon $startAt,
        Carbon $endAt,
        ?Procedure $procedure,
        ?Room $room,
        ?int $patientId,
        ?int $ignoreAppointmentId = null
    ): array {
        $availability = new AvailabilityService();
        $plan = $availability->getDailyPlan($doctor, $date);

        $result = [
            'hard' => [],
            'soft' => [],
        ];

        if (isset($plan['reason'])) {
            $result['hard'][] = ['code' => $plan['reason'], 'message' => 'Лікар не працює у цю дату'];
            return $result;
        }

        if ($startAt < $plan['start'] || $endAt > $plan['end']) {
            $result['hard'][] = ['code' => 'out_of_day', 'message' => 'Час виходить за межі робочого дня лікаря'];
        }

        if ($plan['break_start'] && $plan['break_end'] && $startAt->between($plan['break_start'], $plan['break_end']->copy()->subMinute())) {
            $result['hard'][] = ['code' => 'doctor_break', 'message' => 'Час потрапляє у перерву лікаря'];
        }

        $doctorConflict = Appointment::where('doctor_id', $doctor->id)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
            ->whereDate('start_at', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->exists();

        if ($doctorConflict) {
            $result['hard'][] = ['code' => 'doctor_busy', 'message' => 'Лікар вже зайнятий у цей час'];
        }

        if ($patientId) {
            $patientConflict = Appointment::where('patient_id', $patientId)
                ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($startAt, $endAt) {
                    $q->where('start_at', '<', $endAt)
                        ->where('end_at', '>', $startAt);
                })
                ->exists();

            if ($patientConflict) {
                $result['soft'][] = ['code' => 'patient_busy', 'message' => 'Пацієнт вже має запис у цей час'];
            }
        }

        if ($procedure && $procedure->requires_room) {
            if (! $room) {
                $result['hard'][] = ['code' => 'room_missing', 'message' => 'Потрібен вільний кабінет для процедури'];
            }
        }

        if ($room) {
            $roomConflict = Appointment::where('room_id', $room->id)
                ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($startAt, $endAt) {
                    $q->where('start_at', '<', $endAt)
                        ->where('end_at', '>', $startAt);
                })
                ->exists();

            if ($roomConflict) {
                $result['hard'][] = ['code' => 'room_busy', 'message' => 'Кабінет зайнятий у цей час'];
            }
        }

        return $result;
    }
}
