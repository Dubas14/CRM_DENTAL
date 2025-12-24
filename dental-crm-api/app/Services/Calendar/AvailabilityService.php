<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
            'start' => Carbon::parse($date->toDateString() . ' ' . $startTime),
            'end'   => Carbon::parse($date->toDateString() . ' ' . $endTime),
            'break_start' => $schedule?->break_start
                ? Carbon::parse($date->toDateString() . ' ' . $schedule->break_start)
                : null,
            'break_end' => $schedule?->break_end
                ? Carbon::parse($date->toDateString() . ' ' . $schedule->break_end)
                : null,
            'slot_duration' => $schedule?->slot_duration_minutes ?? 30,
        ];
    }

    public function getSlots(
        Doctor $doctor,
        Carbon $date,
        int $durationMinutes,
        ?Room $room = null,
        ?Equipment $equipment = null,
        ?int $assistantId = null
    ): array {
        $cacheKey = sprintf(
            'calendar_slots_doctor_%d_%s_%d_room_%s_eq_%s_asst_%s_v%s',
            $doctor->id,
            $date->toDateString(),
            $durationMinutes,
            $room?->id ?? 'any',
            $equipment?->id ?? 'any',
            $assistantId ?? 'any',
            $this->getSlotsCacheVersion($doctor->id, $date),
        );

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use (
            $doctor,
            $date,
            $durationMinutes,
            $room,
            $equipment,
            $assistantId
        ) {
            $plan = $this->getDailyPlan($doctor, $date);

            if (isset($plan['reason'])) {
                return ['slots' => [], 'reason' => $plan['reason']];
            }

            $appointments = Appointment::where('doctor_id', $doctor->id)
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->get();

            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $blocks = CalendarBlock::where('doctor_id', $doctor->id)
                ->where('start_at', '<', $dayEnd)
                ->where('end_at', '>', $dayStart)
                ->get();

            $roomAppointments = $room
                ? Appointment::where('room_id', $room->id)
                    ->whereDate('start_at', $date)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->get()
                : collect();

            $roomBlocks = $room
                ? CalendarBlock::where('room_id', $room->id)
                    ->where('start_at', '<', $dayEnd)
                    ->where('end_at', '>', $dayStart)
                    ->get()
                : collect();

            $equipmentAppointments = $equipment
                ? Appointment::where('equipment_id', $equipment->id)
                    ->whereDate('start_at', $date)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->get()
                : collect();

            $equipmentBlocks = $equipment
                ? CalendarBlock::where('equipment_id', $equipment->id)
                    ->where('start_at', '<', $dayEnd)
                    ->where('end_at', '>', $dayStart)
                    ->get()
                : collect();

            $assistantAppointments = $assistantId
                ? Appointment::where('assistant_id', $assistantId)
                    ->whereDate('start_at', $date)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->get()
                : collect();

            $assistantBlocks = $assistantId
                ? CalendarBlock::where('assistant_id', $assistantId)
                    ->where('start_at', '<', $dayEnd)
                    ->where('end_at', '>', $dayStart)
                    ->get()
                : collect();

            $period = new CarbonPeriod(
                $plan['start'],
                "{$plan['slot_duration']} minutes",
                $plan['end']->copy()->subMinutes($durationMinutes)
            );

            $slots = [];

            foreach ($period as $start) {
                $end = $start->copy()->addMinutes($durationMinutes);

                // break
                if ($plan['break_start'] && $plan['break_end']) {
                    if ($start->between($plan['break_start'], $plan['break_end']->copy()->subMinute())) {
                        continue;
                    }
                }

                if ($end > $plan['end']) {
                    continue;
                }

                if ($this->hasConflict($appointments, $start, $end)) {
                    continue;
                }

                if ($this->hasConflict($blocks, $start, $end)) {
                    continue;
                }

                if ($room && $this->hasConflict($roomAppointments, $start, $end)) {
                    continue;
                }

                if ($room && $this->hasConflict($roomBlocks, $start, $end)) {
                    continue;
                }

                if ($equipment && $this->hasConflict($equipmentAppointments, $start, $end)) {
                    continue;
                }

                if ($equipment && $this->hasConflict($equipmentBlocks, $start, $end)) {
                    continue;
                }

                if ($assistantId && $this->hasConflict($assistantAppointments, $start, $end)) {
                    continue;
                }

                if ($assistantId && $this->hasConflict($assistantBlocks, $start, $end)) {
                    continue;
                }

                $slots[] = [
                    'start' => $start->format('H:i'),
                    'end'   => $end->format('H:i'),
                ];
            }

            return ['slots' => $slots];
        });
    }

    public function hasConflict(Collection $appointments, Carbon $start, Carbon $end): bool
    {
        return $appointments->contains(function ($entry) use ($start, $end) {
            return $start < $entry->end_at && $end > $entry->start_at;
        });
    }

    public static function bumpSlotsCacheVersion(int $doctorId, Carbon $date): void
    {
        $versionKey = self::slotsCacheVersionKey($doctorId, $date);

        Cache::add($versionKey, 1, now()->addDays(7));
        Cache::increment($versionKey);
    }

    private function getSlotsCacheVersion(int $doctorId, Carbon $date): int
    {
        $versionKey = self::slotsCacheVersionKey($doctorId, $date);

        Cache::add($versionKey, 1, now()->addDays(7));

        return (int) Cache::get($versionKey, 1);
    }

    private static function slotsCacheVersionKey(int $doctorId, Carbon $date): string
    {
        return sprintf('calendar_slots_version_doctor_%d_%s', $doctorId, $date->toDateString());
    }

    public function resolveRoom(?Room $room, Procedure $procedure, Carbon $date, Carbon $start, Carbon $end, int $clinicId): ?Room
    {
        if ($room) return $room;

        if (!$procedure->requires_room && !$procedure->default_room_id) {
            return null;
        }

        $candidateQuery = Room::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true);

        if ($procedure->default_room_id) {
            $candidateQuery->where('id', $procedure->default_room_id);
        }

        foreach ($candidateQuery->get() as $candidate) {
            $hasConflict = Appointment::where('room_id', $candidate->id)
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_at', '<', $end)->where('end_at', '>', $start);
                })
                ->exists();

            if (! $hasConflict) return $candidate;
        }

        return null;
    }

    public function resolveEquipment(?Equipment $equipment, Procedure $procedure, Carbon $date, Carbon $start, Carbon $end, int $clinicId): ?Equipment
    {
        if ($equipment) return $equipment;

        if (! $procedure->equipment_id) {
            return null;
        }

        $equipments = Equipment::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->where('id', $procedure->equipment_id)
            ->get();

        foreach ($equipments as $candidate) {
            $hasConflict = Appointment::where('equipment_id', $candidate->id)
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_at', '<', $end)->where('end_at', '>', $start);
                })
                ->exists();

            if (! $hasConflict) return $candidate;
        }

        return null;
    }

    public function suggestSlots(
        Doctor $doctor,
        Carbon $fromDate,
        int $durationMinutes,
        ?Room $room = null,
        ?Equipment $equipment = null,
        int $limit = 5,
        ?string $preferredTimeOfDay = null,
        ?int $assistantId = null
    ): array {
        $slots = [];
        $cursor = $fromDate->copy();
        $safetyCounter = 0;

        while (count($slots) < $limit && $safetyCounter < 60) {
            $daily = $this->getSlots($doctor, $cursor, $durationMinutes, $room, $equipment, $assistantId);

            foreach ($daily['slots'] as $slot) {
                $slotStart = Carbon::createFromFormat('H:i', $slot['start']);
                $score = 100 - $fromDate->diffInDays($cursor);

                if ($preferredTimeOfDay) {
                    $isPreferredTime = match ($preferredTimeOfDay) {
                        'morning' => $slotStart->betweenIncluded($slotStart->copy()->setTime(6, 0), $slotStart->copy()->setTime(11, 59)),
                        'afternoon' => $slotStart->betweenIncluded($slotStart->copy()->setTime(12, 0), $slotStart->copy()->setTime(16, 59)),
                        'evening' => $slotStart->betweenIncluded($slotStart->copy()->setTime(17, 0), $slotStart->copy()->setTime(20, 59)),
                        default => false,
                    };

                    if ($isPreferredTime) $score += 20;
                }

                $slots[] = [
                    'date' => $cursor->toDateString(),
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                    'score' => $score,
                ];
            }

            $cursor->addDay();
            $safetyCounter++;
        }

        usort($slots, fn($a, $b) => $b['score'] <=> $a['score']);
        $topSlots = array_slice($slots, 0, $limit);

        return array_map(fn ($slot) => [
            'date' => $slot['date'],
            'start' => $slot['start'],
            'end' => $slot['end'],
        ], $topSlots);
    }
}
