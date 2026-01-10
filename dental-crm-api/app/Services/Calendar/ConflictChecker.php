<?php

namespace App\Services\Calendar;

use App\Models\Appointment;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Procedure;
use App\Models\Room;
use Carbon\Carbon;
use App\Helpers\DebugLogHelper;

class ConflictChecker
{
    public function evaluate(
        Doctor $doctor,
        Carbon $date,
        Carbon $startAt,
        Carbon $endAt,
        ?Procedure $procedure,
        ?Room $room,
        ?Equipment $equipment,
        ?int $patientId,
        ?int $ignoreAppointmentId = null,
        ?int $assistantId = null
    ): array {
        $availability = new AvailabilityService;
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

        // #region agent log
        DebugLogHelper::write('ConflictChecker.php:51', 'Checking doctor conflicts', ['doctor_id' => $doctor->id, 'date' => $date->toDateString(), 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'ignoreAppointmentId' => $ignoreAppointmentId], 'conflictCheck', 'A');
        // #endregion
        
        $doctorConflicts = Appointment::where('doctor_id', $doctor->id)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
            ->whereDate('start_at', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->get();
        
        // #region agent log
        DebugLogHelper::write('ConflictChecker.php:62', 'Doctor conflicts query result', ['doctor_id' => $doctor->id, 'conflicts_count' => $doctorConflicts->count(), 'conflicts' => $doctorConflicts->map(fn($a) => ['id' => $a->id, 'start_at' => $a->start_at, 'end_at' => $a->end_at, 'status' => $a->status, 'patient_id' => $a->patient_id, 'room_id' => $a->room_id, 'assistant_id' => $a->assistant_id])->toArray()], 'conflictCheck', 'A');
        // #endregion
        
        if ($doctorConflicts->isNotEmpty()) {
            // #region agent log
            DebugLogHelper::write('ConflictChecker.php:73', 'Doctor conflict found', ['doctor_id' => $doctor->id, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'conflicting_appointments' => $doctorConflicts->map(fn($a) => ['id' => $a->id, 'start_at' => $a->start_at, 'end_at' => $a->end_at, 'status' => $a->status])->toArray()], 'conflictCheck', 'A');
            // #endregion
            $result['hard'][] = ['code' => 'doctor_busy', 'message' => 'Лікар вже зайнятий у цей час'];
        }

        $doctorBlockConflict = CalendarBlock::where('doctor_id', $doctor->id)
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();

        if ($doctorBlockConflict) {
            $result['hard'][] = ['code' => 'doctor_blocked', 'message' => 'Час заблокований у календарі лікаря'];
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

        // М'які конфлікти: перевірка пікових годин
        $peakHours = config('calendar.peak_hours', ['09:00-12:00', '14:00-18:00']);
        $startTime = $startAt->format('H:i');
        foreach ($peakHours as $peakRange) {
            [$peakStart, $peakEnd] = explode('-', $peakRange);
            if ($startTime >= $peakStart && $startTime < $peakEnd) {
                $result['soft'][] = ['code' => 'peak_hours', 'message' => 'Це пікові години - можливі затримки'];
                break;
            }
        }

        // М'які конфлікти: перевірка поспіль >2 процедур
        $timeBetween = config('calendar.time_between_appointments', 5);
        $threshold = config('calendar.consecutive_threshold', 2);

        // Отримуємо всі записи лікаря на цей день
        $dayAppointments = Appointment::where('doctor_id', $doctor->id)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
            ->whereDate('start_at', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('start_at')
            ->get();

        // Додаємо поточний запис до списку для аналізу
        $allAppointments = $dayAppointments->map(function ($appt) {
            return [
                'start' => Carbon::parse($appt->start_at),
                'end' => Carbon::parse($appt->end_at),
            ];
        })->push([
            'start' => $startAt,
            'end' => $endAt,
        ])->sortBy('start')->values();

        // Рахуємо найдовшу послідовність поспіль процедур
        $maxConsecutive = 1;
        $currentConsecutive = 1;

        for ($i = 1; $i < $allAppointments->count(); $i++) {
            $prev = $allAppointments[$i - 1];
            $curr = $allAppointments[$i];

            // Якщо попередня процедура закінчується незадовго до початку поточної
            if ($curr['start']->diffInMinutes($prev['end']) <= $timeBetween) {
                $currentConsecutive++;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            } else {
                $currentConsecutive = 1;
            }
        }

        if ($maxConsecutive > $threshold) {
            $result['soft'][] = [
                'code' => 'consecutive_appointments',
                'message' => "Лікар має {$maxConsecutive} процедури поспіль - можливі затримки",
            ];
        }

        // М'які конфлікти: перевірка передоплати
        if ($patientId && $procedure && $procedure->requires_prepayment) {
            $hasPrepayment = Invoice::where('patient_id', $patientId)
                ->where(function ($q) use ($procedure, $ignoreAppointmentId) {
                    // Перевіряємо передоплату для цієї процедури або для конкретного запису
                    $q->where(function ($subQ) use ($procedure) {
                        $subQ->where('procedure_id', $procedure->id)
                            ->orWhereNull('procedure_id');
                    });

                    // Якщо це оновлення запису, перевіряємо передоплату для старого запису
                    if ($ignoreAppointmentId) {
                        $q->orWhere('appointment_id', $ignoreAppointmentId);
                    }
                })
                ->where('status', Invoice::STATUS_PAID)
                ->where('is_prepayment', true)
                ->whereRaw('paid_amount >= amount') // Перевіряємо, що сплачено повну суму
                ->exists();

            if (! $hasPrepayment) {
                $result['soft'][] = [
                    'code' => 'missing_prepayment',
                    'message' => 'Відсутня передоплата за процедуру. Рекомендується отримати передоплату перед підтвердженням запису.',
                ];
            }
        }

        if ($procedure && $procedure->requires_room) {
            if (! $room) {
                $result['hard'][] = ['code' => 'room_missing', 'message' => 'Потрібен вільний кабінет для процедури'];
            }
        }

        if ($room) {
            $roomConflictQuery = Appointment::where('room_id', $room->id)
                ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($startAt, $endAt) {
                    $q->where('start_at', '<', $endAt)
                        ->where('end_at', '>', $startAt);
                });
            
            $roomConflictAppointments = $roomConflictQuery->get();
            $roomConflict = $roomConflictQuery->exists();

            // #region agent log
            DebugLogHelper::write('ConflictChecker.php:209', 'Room conflict check', ['room_id' => $room->id, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'ignoreAppointmentId' => $ignoreAppointmentId, 'conflict_exists' => $roomConflict, 'conflicting_appointments' => $roomConflictAppointments->map(fn($a) => ['id' => $a->id, 'doctor_id' => $a->doctor_id, 'start_at' => $a->start_at, 'end_at' => $a->end_at, 'status' => $a->status])->toArray()], 'conflictCheck', 'B');
            // #endregion

            if ($roomConflict) {
                // #region agent log
                DebugLogHelper::write('ConflictChecker.php:214', 'Room conflict found', ['room_id' => $room->id, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString()], 'conflictCheck', 'B');
                // #endregion
                $result['hard'][] = ['code' => 'room_busy', 'message' => 'Кабінет зайнятий у цей час'];
            }

            $roomBlockConflict = CalendarBlock::where('room_id', $room->id)
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->exists();

            if ($roomBlockConflict) {
                $result['hard'][] = ['code' => 'room_blocked', 'message' => 'Кабінет заблокований у цей час'];
            }
        }

        if ($procedure?->equipment_id) {
            if (! $equipment) {
                $result['hard'][] = ['code' => 'equipment_missing', 'message' => 'Потрібне обладнання для процедури'];
            } else {
                $equipmentConflict = Appointment::where('equipment_id', $equipment->id)
                    ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
                    ->whereDate('start_at', $date)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->where(function ($q) use ($startAt, $endAt) {
                        $q->where('start_at', '<', $endAt)
                            ->where('end_at', '>', $startAt);
                    })
                    ->exists();

                if ($equipmentConflict) {
                    $result['hard'][] = ['code' => 'equipment_busy', 'message' => 'Обладнання зайняте у цей час'];
                }

                $equipmentBlockConflict = CalendarBlock::where('equipment_id', $equipment->id)
                    ->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt)
                    ->exists();

                if ($equipmentBlockConflict) {
                    $result['hard'][] = ['code' => 'equipment_blocked', 'message' => 'Обладнання заблоковане у цей час'];
                }
            }
        }
        // Перевірка асистента: якщо передано assistantId (незалежно від процедури)
        if ($assistantId) {
            $assistantConflictQuery = Appointment::where('assistant_id', $assistantId)
                ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '<>', $ignoreAppointmentId))
                ->whereDate('start_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($startAt, $endAt) {
                    $q->where('start_at', '<', $endAt)
                        ->where('end_at', '>', $startAt);
                });
            
            $assistantConflictAppointments = $assistantConflictQuery->get();
            $assistantConflict = $assistantConflictQuery->exists();

            // #region agent log
            DebugLogHelper::write('ConflictChecker.php:272', 'Assistant conflict check', ['assistant_id' => $assistantId, 'procedure_requires_assistant' => $procedure?->requires_assistant ?? false, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'ignoreAppointmentId' => $ignoreAppointmentId, 'conflict_exists' => $assistantConflict, 'conflicting_appointments' => $assistantConflictAppointments->map(fn($a) => ['id' => $a->id, 'doctor_id' => $a->doctor_id, 'start_at' => $a->start_at, 'end_at' => $a->end_at, 'status' => $a->status])->toArray()], 'conflictCheck', 'C');
            // #endregion

            if ($assistantConflict) {
                // #region agent log
                DebugLogHelper::write('ConflictChecker.php:277', 'Assistant conflict found', ['assistant_id' => $assistantId, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString()], 'conflictCheck', 'C');
                // #endregion
                $result['hard'][] = ['code' => 'assistant_busy', 'message' => 'Асистент зайнятий у цей час'];
            }

            $assistantBlockConflict = CalendarBlock::where('assistant_id', $assistantId)
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->exists();

            if ($assistantBlockConflict) {
                $result['hard'][] = ['code' => 'assistant_blocked', 'message' => 'Асистент заблокований у цей час'];
            }
        }

        return $result;
    }
}
