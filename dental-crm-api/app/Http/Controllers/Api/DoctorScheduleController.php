<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\Access\DoctorAccessService;
use App\Models\Procedure;
use App\Models\Room;
use App\Services\Calendar\AvailabilityService;


class DoctorScheduleController extends Controller
{

    public function updateSchedule(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageDoctor($user, $doctor)) {
            abort(403, 'У вас немає доступу до зміни розкладу цього лікаря');
        }

        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.break_start' => ['nullable', 'date_format:H:i'],
            'schedules.*.break_end' => ['nullable', 'date_format:H:i'],
            'schedules.*.slot_duration_minutes' => ['nullable', 'integer', 'min:5'],
            'exceptions' => ['sometimes', 'array'],
            'exceptions.*.date' => ['required', 'date'],
            'exceptions.*.type' => ['required', 'in:day_off,override'],
            'exceptions.*.start_time' => ['nullable', 'date_format:H:i'],
            'exceptions.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        Schedule::where('doctor_id', $doctor->id)->delete();

        foreach ($validated['schedules'] as $item) {
            Schedule::create([
                'doctor_id' => $doctor->id,
                'weekday' => $item['weekday'],
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'break_start' => $item['break_start'] ?? null,
                'break_end' => $item['break_end'] ?? null,
                'slot_duration_minutes' => $item['slot_duration_minutes'] ?? 30,
            ]);
        }

        if (isset($validated['exceptions'])) {
            ScheduleException::where('doctor_id', $doctor->id)->delete();

            foreach ($validated['exceptions'] as $exception) {
                ScheduleException::create([
                    'doctor_id' => $doctor->id,
                    'date' => $exception['date'],
                    'type' => $exception['type'],
                    'start_time' => $exception['start_time'] ?? null,
                    'end_time' => $exception['end_time'] ?? null,
                ]);
            }
        }

        return response()->json(['status' => 'updated']);
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
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
        ]);

        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду слотів цього лікаря');
        }

        $date = Carbon::parse($validated['date'])->startOfDay();
        $procedure = isset($validated['procedure_id']) ? Procedure::find($validated['procedure_id']) : null;
        $room = isset($validated['room_id']) ? Room::find($validated['room_id']) : null;

        $availability = new AvailabilityService();
        $plan = $availability->getDailyPlan($doctor, $date);

        if (isset($plan['reason'])) {
            return response()->json([
                'date'   => $date->toDateString(),
                'slots'  => [],
                'reason' => $plan['reason'],
            ]);
        }

        $duration = $procedure?->duration_minutes ?? $plan['slot_duration'];
        $slots = $availability->getSlots($doctor, $date, $duration, $room);

        return response()->json([
            'date'  => $date->toDateString(),
            'slots' => $slots['slots'],
            'reason' => $slots['reason'] ?? null,
            'duration_minutes' => $duration,
        ]);
    }

    public function recommended(Request $request, Doctor $doctor)
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
        ]);

        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду слотів цього лікаря');
        }

        $fromDate = Carbon::parse($validated['from_date'])->startOfDay();
        $procedure = isset($validated['procedure_id']) ? Procedure::find($validated['procedure_id']) : null;
        $room = isset($validated['room_id']) ? Room::find($validated['room_id']) : null;

        $availability = new AvailabilityService();
        $plan = $availability->getDailyPlan($doctor, $fromDate);
        $duration = $procedure?->duration_minutes ?? ($plan['slot_duration'] ?? 30);
        $slots = $availability->suggestSlots($doctor, $fromDate, $duration, $room, $validated['limit'] ?? 5);

        return response()->json([
            'from_date' => $fromDate->toDateString(),
            'slots' => $slots,
            'duration_minutes' => $duration,
        ]);
    }
}
