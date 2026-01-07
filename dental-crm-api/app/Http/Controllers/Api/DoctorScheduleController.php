<?php

namespace App\Http\Controllers\Api;

use App\Events\ScheduleChanged;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Services\Access\DoctorAccessService;
use App\Services\Calendar\AvailabilityService;
use App\Services\Calendar\RescheduleService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    public function updateSchedule(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (! DoctorAccessService::canManageDoctor($user, $doctor)) {
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

        $rescheduleQueue = [];

        if (isset($validated['exceptions'])) {
            ScheduleException::where('doctor_id', $doctor->id)->delete();
            $rescheduleService = new RescheduleService;

            foreach ($validated['exceptions'] as $exception) {
                $createdException = ScheduleException::create([
                    'doctor_id' => $doctor->id,
                    'date' => $exception['date'],
                    'type' => $exception['type'],
                    'start_time' => $exception['start_time'] ?? null,
                    'end_time' => $exception['end_time'] ?? null,
                ]);

                if (in_array($createdException->type, ['day_off', 'override'], true)) {
                    $from = Carbon::parse($createdException->date)->startOfDay();
                    $to = Carbon::parse($createdException->date)->endOfDay();

                    $rescheduleQueue = array_merge(
                        $rescheduleQueue,
                        $rescheduleService->buildRescheduleQueue($doctor, $from, $to)
                    );
                }
            }

            if (! empty($rescheduleQueue)) {
                $candidateIds = $rescheduleService->storeRescheduleCandidates($doctor, $rescheduleQueue);
                ScheduleChanged::dispatch($doctor->id, $candidateIds);
            }
        }

        // Оновлення розкладу має інвалідовувати кеш слотів на найближчий період.
        $this->invalidateSlotsCache($doctor->id, Carbon::today(), Carbon::today()->addDays(30));

        if (! empty($validated['exceptions'])) {
            foreach ($validated['exceptions'] as $exception) {
                $date = Carbon::parse($exception['date'])->startOfDay();
                AvailabilityService::bumpSlotsCacheVersion($doctor->id, $date);
            }
        }

        return response()->json([
            'status' => 'updated',
            'reschedule_queue' => $rescheduleQueue,
        ]);
    }

    public function schedule(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
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
            'doctor' => $doctor,
            'schedules' => $schedules,
            'exceptions' => $exceptions,
        ]);
    }

    public function slots(Request $request, Doctor $doctor)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        $user = $request->user();

        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду слотів цього лікаря');
        }

        $date = Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay();

        $procedure = isset($validated['procedure_id']) ? Procedure::find($validated['procedure_id']) : null;
        $room = isset($validated['room_id']) ? Room::find($validated['room_id']) : null;
        $equipment = isset($validated['equipment_id']) ? Equipment::find($validated['equipment_id']) : null;
        $assistantId = $validated['assistant_id'] ?? null;

        $availability = new AvailabilityService;
        $clinicId = $validated['clinic_id'] ?? $doctor->clinic_id;
        $plan = $availability->getDailyPlan($doctor, $date, $clinicId);

        if (isset($plan['reason'])) {
            return response()->json([
                'date' => $date->toDateString(),
                'slots' => [],
                'reason' => $plan['reason'],
                'vacation_to' => $plan['vacation_to'] ?? ($doctor->vacation_to ?? null),
            ]);
        }

        $duration = $availability->resolveProcedureDuration(
            $doctor,
            $procedure,
            $plan['slot_duration'] ?? 30
        );

        $resolvedEquipment = $equipment ?? $procedure?->equipment;

        $slots = $availability->getSlots(
            $doctor,
            $date,
            $duration,
            $procedure,
            $room,
            $resolvedEquipment,
            $assistantId,
            $clinicId
        );

        return response()->json([
            'date' => $date->toDateString(),
            'slots' => $slots['slots'],
            'reason' => $slots['reason'] ?? null,
            'duration_minutes' => $duration,
            'room' => $room,
            'equipment' => $resolvedEquipment,
            'assistant_id' => $assistantId,
        ]);
    }

    public function recommended(Request $request, Doctor $doctor)
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        $user = $request->user();

        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду слотів цього лікаря');
        }

        $fromDate = Carbon::parse($validated['from_date'])->startOfDay();

        $procedure = isset($validated['procedure_id']) ? Procedure::find($validated['procedure_id']) : null;
        $room = isset($validated['room_id']) ? Room::find($validated['room_id']) : null;
        $equipment = isset($validated['equipment_id']) ? Equipment::find($validated['equipment_id']) : null;
        $assistantId = $validated['assistant_id'] ?? null;

        $availability = new AvailabilityService;
        $clinicId = $validated['clinic_id'] ?? $doctor->clinic_id;
        $plan = $availability->getDailyPlan($doctor, $fromDate, $clinicId);

        $duration = $availability->resolveProcedureDuration(
            $doctor,
            $procedure,
            $plan['slot_duration'] ?? 30
        );
        $resolvedEquipment = $equipment ?? $procedure?->equipment;

        $slots = $availability->suggestSlots(
            $doctor,
            $fromDate,
            $duration,
            $procedure,
            $room,
            $resolvedEquipment,
            $validated['limit'] ?? 5,
            null,
            $assistantId,
            $clinicId
        );

        return response()->json([
            'from_date' => $fromDate->toDateString(),
            'slots' => $slots,
            'duration_minutes' => $duration,
            'room' => $room,
            'equipment' => $resolvedEquipment,
            'assistant_id' => $assistantId,
        ]);
    }

    private function invalidateSlotsCache(int $doctorId, Carbon $startAt, Carbon $endAt): void
    {
        $period = CarbonPeriod::create($startAt->copy()->startOfDay(), '1 day', $endAt->copy()->startOfDay());

        foreach ($period as $date) {
            AvailabilityService::bumpSlotsCacheVersion($doctorId, $date);
        }
    }
}
