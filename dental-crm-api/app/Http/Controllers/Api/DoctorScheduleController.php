<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Services\Access\DoctorAccessService;


class DoctorScheduleController extends Controller
{

    public function updateSchedule(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageDoctor($user, $doctor)) {
            abort(403, 'У вас немає доступу до зміни розкладу цього лікаря');
        }

        // TODO: тут ми пізніше напишемо логіку оновлення базового розкладу (schedules)
    }

    // базовий розклад + винятки
    public function schedule(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду розкладу цього лікаря');
        }
        $schedules = Schedule::where('doctor_id', $doctor->id)
            ->orderBy('weekday')
            ->get();

        $exceptions = ScheduleException::where('doctor_id', $doctor->id)
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'doctor'     => $doctor,
            'schedules'  => $schedules,
            'exceptions' => $exceptions,
        ]);
    }


    // вільні слоти на конкретну дату
    public function slots(Request $request, Doctor $doctor)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду слотів цього лікаря');
        }


        $date = Carbon::parse($validated['date'])->startOfDay();
        $weekday = (int) $date->isoWeekday(); // 1 (Mon) ... 7 (Sun)

        // виняток
        $exception = ScheduleException::where('doctor_id', $doctor->id)
            ->whereDate('date', $date)
            ->first();

        if ($exception && $exception->type === 'day_off') {
            return response()->json([
                'date'   => $date->toDateString(),
                'slots'  => [],
                'reason' => 'day_off',
            ]);
        }

        // базовий розклад
        $schedule = Schedule::where('doctor_id', $doctor->id)
            ->where('weekday', $weekday)
            ->first();

        if (!$schedule && !$exception) {
            return response()->json([
                'date'   => $date->toDateString(),
                'slots'  => [],
                'reason' => 'no_schedule',
            ]);
        }

        // години роботи з урахуванням override
        $startTime = $exception && $exception->type === 'override'
            ? $exception->start_time
            : ($schedule->start_time ?? $exception->start_time);

        $endTime = $exception && $exception->type === 'override'
            ? $exception->end_time
            : ($schedule->end_time ?? $exception->end_time);

        if (!$startTime || !$endTime) {
            return response()->json([
                'date'   => $date->toDateString(),
                'slots'  => [],
                'reason' => 'invalid_schedule',
            ]);
        }

        $slotDuration = $schedule->slot_duration_minutes ?? 30;

        $workStart = Carbon::parse($date->toDateString() . ' ' . $startTime);
        $workEnd   = Carbon::parse($date->toDateString() . ' ' . $endTime);

        // перерва (опційно)
        $breakStart = $schedule?->break_start
            ? Carbon::parse($date->toDateString() . ' ' . $schedule->break_start)
            : null;
        $breakEnd = $schedule?->break_end
            ? Carbon::parse($date->toDateString() . ' ' . $schedule->break_end)
            : null;

        // існуючі записи лікаря на цей день
        $appointments = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('start_at', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get();

        $slots = [];

        $period = new CarbonPeriod($workStart, "{$slotDuration} minutes", $workEnd->copy()->subMinutes($slotDuration));

        foreach ($period as $start) {
            $end = $start->copy()->addMinutes($slotDuration);

            // в перерву не записуємо
            if ($breakStart && $breakEnd && $start->between($breakStart, $breakEnd->subMinute())) {
                continue;
            }

            // перевірка конфліктів із записами
            $hasConflict = $appointments->contains(function (Appointment $appt) use ($start, $end) {
                return $start < $appt->end_at && $end > $appt->start_at;
            });

            if ($hasConflict) {
                continue;
            }

            $slots[] = [
                'start' => $start->format('H:i'),
                'end'   => $end->format('H:i'),
            ];
        }

        return response()->json([
            'date'  => $date->toDateString(),
            'slots' => $slots,
        ]);
    }
}
