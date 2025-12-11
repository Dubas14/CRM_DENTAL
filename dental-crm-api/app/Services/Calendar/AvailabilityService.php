<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function getDailyPlan(Doctor $doctor, Carbon $date): array
    {
        $weekday = (int) $date->isoWeekday();

        $exception = ScheduleException::where('doctor_id', $doctor->id)
            ->whereDate('date', $date)
            ->first();

        if ($exception && $exception->type === 'day_off') {
            return ['reason' => 'day_off'];
        }

        $schedule = Schedule::where('doctor_id', $doctor->id)
            ->where('weekday', $weekday)
            ->first();

        if (!$schedule && !$exception) {
            return ['reason' => 'no_schedule'];
        }

        $startTime = $exception && $exception->type === 'override'
            ? $exception->start_time
            : ($schedule?->start_time ?? $exception?->start_time);

        $endTime = $exception && $exception->type === 'override'
            ? $exception->end_time
            : ($schedule?->end_time ?? $exception?->end_time);

        if (!$startTime || !$endTime) {
            return ['reason' => 'invalid_schedule'];
        }

        return [
            'start' => Carbon::parse($date->toDateString().' '.$startTime),
            'end'   => Carbon::parse($date->toDateString().' '.$endTime),
            'break_start' => $schedule?->break_start
                ? Carbon::parse($date->toDateString().' '.$schedule->break_start)
                : null,
            'break_end' => $schedule?->break_end
                ? Carbon::parse($date->toDateString().' '.$schedule->break_end)
                : null,
            'slot_duration' => $schedule?->slot_duration_minutes ?? 30,
        ];
    }

    public function getSlots(Doctor $doctor, Carbon $date, int $durationMinutes, ?Room $room = null): array
    {
        $plan = $this->getDailyPlan($doctor, $date);

        if (isset($plan['reason'])) {
            return ['slots' => [], 'reason' => $plan['reason']];
        }

        $appointments = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('start_at', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get();

        $roomAppointments = $room ? Appointment::where('room_id', $room->id)
            ->whereDate('start_at', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get() : collect();

        $period = new CarbonPeriod($plan['start'], "{$plan['slot_duration']} minutes", $plan['end']->copy()->subMinutes($durationMinutes));
        $slots = [];

        foreach ($period as $start) {
            $end = $start->copy()->addMinutes($durationMinutes);

            if ($plan['break_start'] && $plan['break_end'] && $start->between($plan['break_start'], $plan['break_end']->copy()->subMinute())) {
                continue;
            }

            if ($end > $plan['end']) {
                continue;
            }

            if ($this->hasConflict($appointments, $start, $end)) {
                continue;
            }

            if ($room && $this->hasConflict($roomAppointments, $start, $end)) {
                continue;
            }

            $slots[] = [
                'start' => $start->format('H:i'),
                'end'   => $end->format('H:i'),
            ];
        }

        return ['slots' => $slots];
    }

    public function hasConflict(Collection $appointments, Carbon $start, Carbon $end): bool
    {
        return $appointments->contains(function (Appointment $appt) use ($start, $end) {
            return $start < $appt->end_at && $end > $appt->start_at;
        });
    }

    public function resolveRoom(?Room $room, Procedure $procedure, Carbon $date, Carbon $start, Carbon $end, int $clinicId): ?Room
    {
        if ($room) {
            return $room;
        }

        if (!$procedure->requires_room && !$procedure->default_room_id) {
            return null;
        }

        $candidateQuery = Room::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true);

        if ($procedure->default_room_id) {
            $candidateQuery->where('id', $procedure->default_room_id);
        }

        $rooms = $candidateQuery->get();

        foreach ($rooms as $candidate) {
            $hasConflict = Appointment::where('room_id', $candidate->id)
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_at', '<', $end)->where('end_at', '>', $start);
                })
                ->exists();

            if (! $hasConflict) {
                return $candidate;
            }
        }

        return null;
    }

    public function suggestSlots(Doctor $doctor, Carbon $fromDate, int $durationMinutes, ?Room $room = null, int $limit = 5): array
    {
        $slots = [];
        $cursor = $fromDate->copy();
        $safetyCounter = 0;

        while (count($slots) < $limit && $safetyCounter < 60) {
            $daily = $this->getSlots($doctor, $cursor, $durationMinutes, $room);

            foreach ($daily['slots'] as $slot) {
                $slots[] = [
                    'date' => $cursor->toDateString(),
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                ];

                if (count($slots) >= $limit) {
                    break;
                }
            }

            $cursor->addDay();
            $safetyCounter++;
        }

        return $slots;
    }
}
