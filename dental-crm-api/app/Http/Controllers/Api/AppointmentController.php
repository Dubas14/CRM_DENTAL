<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\Procedure;
use App\Models\ProcedureStep;
use App\Models\Room;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Access\DoctorAccessService;
use App\Services\Calendar\AvailabilityService;
use App\Services\Calendar\ConflictChecker;
use App\Services\Calendar\WaitlistService;
use App\Events\AppointmentCancelled;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],

            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'procedure_step_id' => ['nullable', 'exists:procedure_steps,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],

            'patient_id' => ['nullable', 'exists:patients,id'],
            'is_follow_up' => ['sometimes', 'boolean'],

            'source' => ['nullable', 'string', 'max:50'],
            'comment' => ['nullable', 'string'],

            'waitlist_entry_id' => ['nullable', 'exists:waitlist_entries,id'],
            'allow_soft_conflicts' => ['sometimes', 'boolean'],
        ]);

        $doctor = Doctor::findOrFail($data['doctor_id']);

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до створення запису для цього лікаря');
        }

        $availability = new AvailabilityService();

        $procedure = isset($data['procedure_id']) ? Procedure::find($data['procedure_id']) : null;
        $procedureStep = isset($data['procedure_step_id']) ? ProcedureStep::find($data['procedure_step_id']) : null;
        $room = isset($data['room_id']) ? Room::find($data['room_id']) : null;
        $equipment = isset($data['equipment_id']) ? Equipment::find($data['equipment_id']) : null;
        $assistantId = $data['assistant_id'] ?? null;

        if ($procedureStep) {
            if ($procedure && $procedureStep->procedure_id !== $procedure->id) {
                return response()->json([
                    'message' => 'Етап не належить вибраній процедурі',
                ], 422);
            }
            $procedure = $procedure ?? $procedureStep->procedure;
        }

        $date = Carbon::parse($data['date'])->startOfDay();
        $startAt = Carbon::parse($data['date'] . ' ' . $data['time']);

        $plan = $availability->getDailyPlan($doctor, $date);
        if (isset($plan['reason'])) {
            return response()->json([
                'message' => 'Неможливо створити запис: лікар недоступний',
                'reason' => $plan['reason'],
            ], 422);
        }

        $duration = $procedureStep
            ? $procedureStep->duration_minutes
            : $availability->resolveProcedureDuration(
                $doctor,
                $procedure,
                $plan['slot_duration'] ?? 30
            );
        $endAt = $startAt->copy()->addMinutes($duration);

        if ($procedure && $room && $procedure->rooms()->exists()) {
            $isCompatible = $procedure->rooms()->where('rooms.id', $room->id)->exists();
            if (! $isCompatible) {
                return response()->json([
                    'message' => 'Вибраний кабінет несумісний із процедурою',
                ], 422);
            }
        }

        if ($procedure && $procedure->requires_room) {
            $room = $availability->resolveRoom($room, $procedure, $date, $startAt, $endAt, $doctor->clinic_id);
        }

        if ($procedure && $procedure->requires_room && ! $room) {
            return response()->json([
                'message' => 'Потрібен сумісний кабінет для процедури',
            ], 422);
        }

        if ($procedure?->equipment_id) {
            $equipment = $availability->resolveEquipment(
                $equipment ?? $procedure->equipment,
                $procedure,
                $date,
                $startAt,
                $endAt,
                $doctor->clinic_id
            );
        }

        $conflicts = (new ConflictChecker())->evaluate(
            $doctor,
            $date,
            $startAt,
            $endAt,
            $procedure,
            $room,
            $equipment,
            $data['patient_id'] ?? null,
            null,
            $assistantId
        );

        if (!empty($conflicts['hard'])) {
            return response()->json([
                'message' => 'Неможливо створити запис через конфлікти',
                'hard_conflicts' => $conflicts['hard'],
            ], 422);
        }

        if (!empty($conflicts['soft']) && !($data['allow_soft_conflicts'] ?? false)) {
            return response()->json([
                'message' => 'Виявлено можливі конфлікти',
                'soft_conflicts' => $conflicts['soft'],
            ], 409);
        }

        $appointment = Appointment::create([
            'clinic_id' => $doctor->clinic_id,
            'doctor_id' => $doctor->id,

            'procedure_id' => $procedure?->id,
            'procedure_step_id' => $procedureStep?->id,
            'room_id' => $room?->id,
            'equipment_id' => $equipment?->id,

            'assistant_id' => $assistantId,
            'patient_id' => $data['patient_id'] ?? null,

            'is_follow_up' => (bool)($data['is_follow_up'] ?? false),

            'start_at' => $startAt,
            'end_at' => $endAt,

            'status' => 'planned',
            'source' => $data['source'] ?? 'crm',
            'comment' => $procedureStep
                ? trim(($data['comment'] ?? '') . ' [Етап: ' . $procedureStep->name . ']') ?: null
                : ($data['comment'] ?? null),
        ]);

        if (!empty($data['waitlist_entry_id'])) {
            WaitlistEntry::where('id', $data['waitlist_entry_id'])->update(['status' => 'booked']);
        }

        $appointment->load([
            'clinic:id,name',
            'doctor:id,full_name,clinic_id',
            'assistant:id,full_name',
            'patient:id,full_name,phone',
            'procedure:id,name,duration_minutes',
            'procedureStep:id,procedure_id,name,duration_minutes,order',
            'room:id,name',
            'equipment:id,name',
        ]);

        $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $appointment->end_at);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function storeSeries(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'procedure_id' => ['required', 'exists:procedures,id'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.procedure_step_id' => ['required', 'exists:procedure_steps,id'],
            'steps.*.date' => ['required', 'date'],
            'steps.*.time' => ['required', 'date_format:H:i'],

            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],

            'patient_id' => ['nullable', 'exists:patients,id'],
            'is_follow_up' => ['sometimes', 'boolean'],

            'source' => ['nullable', 'string', 'max:50'],
            'comment' => ['nullable', 'string'],

            'allow_soft_conflicts' => ['sometimes', 'boolean'],
        ]);

        $doctor = Doctor::findOrFail($data['doctor_id']);

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до створення запису для цього лікаря');
        }

        $procedure = Procedure::findOrFail($data['procedure_id']);

        $stepIds = collect($data['steps'])->pluck('procedure_step_id')->unique()->values();
        $steps = ProcedureStep::query()
            ->whereIn('id', $stepIds)
            ->get()
            ->keyBy('id');

        if ($steps->count() !== $stepIds->count()) {
            return response()->json([
                'message' => 'Один або кілька етапів не знайдено',
            ], 422);
        }

        if ($steps->firstWhere('procedure_id', '!=', $procedure->id)) {
            return response()->json([
                'message' => 'Етапи не належать вибраній процедурі',
            ], 422);
        }

        $availability = new AvailabilityService();

        $room = isset($data['room_id']) ? Room::find($data['room_id']) : null;
        $equipment = isset($data['equipment_id']) ? Equipment::find($data['equipment_id']) : null;
        $assistantId = $data['assistant_id'] ?? null;

        $appointmentsPayload = [];
        $conflictsSummary = [
            'hard' => [],
            'soft' => [],
        ];

        foreach ($data['steps'] as $stepItem) {
            $step = $steps->get($stepItem['procedure_step_id']);
            $date = Carbon::parse($stepItem['date'])->startOfDay();
            $startAt = Carbon::parse($stepItem['date'] . ' ' . $stepItem['time']);

            $plan = $availability->getDailyPlan($doctor, $date);
            if (isset($plan['reason'])) {
                return response()->json([
                    'message' => 'Неможливо створити запис: лікар недоступний',
                    'reason' => $plan['reason'],
                    'procedure_step_id' => $step->id,
                ], 422);
            }

            $duration = $step->duration_minutes;
            $endAt = $startAt->copy()->addMinutes($duration);

            $resolvedRoom = $room;
            if ($procedure->requires_room) {
                $resolvedRoom = $availability->resolveRoom(
                    $resolvedRoom,
                    $procedure,
                    $date,
                    $startAt,
                    $endAt,
                    $doctor->clinic_id
                );
            }

            $resolvedEquipment = $equipment;
            if ($procedure->equipment_id) {
                $resolvedEquipment = $availability->resolveEquipment(
                    $resolvedEquipment ?? $procedure->equipment,
                    $procedure,
                    $date,
                    $startAt,
                    $endAt,
                    $doctor->clinic_id
                );
            }

            $conflicts = (new ConflictChecker())->evaluate(
                $doctor,
                $date,
                $startAt,
                $endAt,
                $procedure,
                $resolvedRoom,
                $resolvedEquipment,
                $data['patient_id'] ?? null,
                null,
                $assistantId
            );

            if (!empty($conflicts['hard'])) {
                $conflictsSummary['hard'][] = [
                    'procedure_step_id' => $step->id,
                    'conflicts' => $conflicts['hard'],
                ];
            }

            if (!empty($conflicts['soft'])) {
                $conflictsSummary['soft'][] = [
                    'procedure_step_id' => $step->id,
                    'conflicts' => $conflicts['soft'],
                ];
            }

            $comment = $data['comment'] ?? '';
            $comment = trim($comment . ' [Етап: ' . $step->name . ']') ?: null;

            $appointmentsPayload[] = [
                'clinic_id' => $doctor->clinic_id,
                'doctor_id' => $doctor->id,
                'procedure_id' => $procedure->id,
                'procedure_step_id' => $step->id,
                'room_id' => $resolvedRoom?->id,
                'equipment_id' => $resolvedEquipment?->id,
                'assistant_id' => $assistantId,
                'patient_id' => $data['patient_id'] ?? null,
                'is_follow_up' => (bool)($data['is_follow_up'] ?? false),
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'planned',
                'source' => $data['source'] ?? 'crm',
                'comment' => $comment,
            ];
        }

        if (!empty($conflictsSummary['hard'])) {
            return response()->json([
                'message' => 'Неможливо створити серію через конфлікти',
                'hard_conflicts' => $conflictsSummary['hard'],
            ], 422);
        }

        if (!empty($conflictsSummary['soft']) && !($data['allow_soft_conflicts'] ?? false)) {
            return response()->json([
                'message' => 'Виявлено можливі конфлікти',
                'soft_conflicts' => $conflictsSummary['soft'],
            ], 409);
        }

        $appointments = DB::transaction(function () use ($appointmentsPayload) {
            return collect($appointmentsPayload)->map(function ($payload) {
                return Appointment::create($payload);
            });
        });

        $appointments->each(function (Appointment $appointment) {
            $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $appointment->end_at);
        });

        $appointments = $appointments->map(fn ($appointment) => $appointment->load([
            'clinic:id,name',
            'doctor:id,full_name,clinic_id',
            'assistant:id,name,first_name,last_name',
            'patient:id,full_name,phone',
            'procedure:id,name,duration_minutes',
            'procedureStep:id,procedure_id,name,duration_minutes,order',
            'room:id,name',
            'equipment:id,name',
        ]));

        return AppointmentResource::collection($appointments)
            ->response()
            ->setStatusCode(201);
    }

    public function doctorAppointments(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду записів цього лікаря');
        }

        $date = $request->query('date'); // YYYY-MM-DD

        $query = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->with([
                'clinic:id,name',
                'doctor:id,full_name,clinic_id',
                'assistant:id,name,first_name,last_name',
                'patient:id,full_name,phone',
                'procedure:id,name,duration_minutes',
                'procedureStep:id,procedure_id,name,duration_minutes,order',
                'room:id,name',
                'equipment:id,name',
            ])
            ->orderBy('start_at');

        if ($date) {
            $query->whereDate('start_at', $date);
        }

        return AppointmentResource::collection($query->get());
    }

    public function update(Request $request, Appointment $appointment)
    {
        $previousDoctorId = $appointment->doctor_id;
        $previousStartAt = $appointment->start_at;
        $previousEndAt = $appointment->end_at;

        $validated = $request->validate([
            'doctor_id' => ['sometimes', 'exists:doctors,id'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'date_format:H:i'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],

            'procedure_id' => ['sometimes', 'nullable', 'exists:procedures,id'],
            'procedure_step_id' => ['sometimes', 'nullable', 'exists:procedure_steps,id'],
            'room_id' => ['sometimes', 'nullable', 'exists:rooms,id'],
            'equipment_id' => ['sometimes', 'nullable', 'exists:equipments,id'],
            'assistant_id' => ['sometimes', 'nullable', 'exists:users,id'],

            'patient_id' => ['sometimes', 'nullable', 'exists:patients,id'],
            'is_follow_up' => ['sometimes', 'boolean'],

            'status' => ['sometimes', 'string', 'in:' . implode(',', Appointment::ALLOWED_STATUSES)],
            'comment' => ['sometimes', 'nullable', 'string'],
            'allow_soft_conflicts' => ['sometimes', 'boolean'],
        ]);

        $doctor = isset($validated['doctor_id'])
            ? Doctor::findOrFail($validated['doctor_id'])
            : $appointment->doctor;

        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до зміни цього запису');
        }

        $procedure = array_key_exists('procedure_id', $validated)
            ? Procedure::find($validated['procedure_id'])
            : $appointment->procedure;

        $procedureStep = array_key_exists('procedure_step_id', $validated)
            ? ProcedureStep::find($validated['procedure_step_id'])
            : $appointment->procedureStep;

        if ($procedureStep) {
            if ($procedure && $procedureStep->procedure_id !== $procedure->id) {
                return response()->json([
                    'message' => 'Етап не належить вибраній процедурі',
                ], 422);
            }
            $procedure = $procedure ?? $procedureStep->procedure;
        }

        $room = array_key_exists('room_id', $validated)
            ? Room::find($validated['room_id'])
            : $appointment->room;

        $equipment = array_key_exists('equipment_id', $validated)
            ? Equipment::find($validated['equipment_id'])
            : $appointment->equipment;

        $assistantId = array_key_exists('assistant_id', $validated)
            ? $validated['assistant_id']
            : $appointment->assistant_id;

        $startAtInput = $validated['start_at'] ?? null;
        $endAtInput = $validated['end_at'] ?? null;

        if ($startAtInput) {
            $startAt = Carbon::parse($startAtInput);
            $date = $startAt->copy()->startOfDay();
        } else {
            $dateValue = $validated['date'] ?? $appointment->start_at->toDateString();
            $timeValue = $validated['time'] ?? $appointment->start_at->format('H:i');
            $date = Carbon::parse($dateValue)->startOfDay();
            $startAt = Carbon::parse($dateValue . ' ' . $timeValue);
        }

        $availability = new AvailabilityService();
        $plan = $availability->getDailyPlan($doctor, $date);

        if (isset($plan['reason'])) {
            return response()->json([
                'message' => 'Неможливо змінити запис: лікар недоступний',
                'reason' => $plan['reason'],
            ], 422);
        }

        $duration = $procedureStep
            ? $procedureStep->duration_minutes
            : $availability->resolveProcedureDuration(
                $doctor,
                $procedure,
                $plan['slot_duration'] ?? 30
            );

        if ($endAtInput) {
            $endAt = Carbon::parse($endAtInput);
            if ($endAt->lessThanOrEqualTo($startAt)) {
                return response()->json([
                    'message' => 'Час завершення має бути пізніше за час початку',
                ], 422);
            }
            $duration = $startAt->diffInMinutes($endAt);
        } else {
            $endAt = $startAt->copy()->addMinutes($duration);
        }

        if ($procedure && $room && $procedure->rooms()->exists()) {
            $isCompatible = $procedure->rooms()->where('rooms.id', $room->id)->exists();
            if (! $isCompatible) {
                return response()->json([
                    'message' => 'Вибраний кабінет несумісний із процедурою',
                ], 422);
            }
        }

        if ($procedure && $procedure->requires_room) {
            $room = $availability->resolveRoom($room, $procedure, $date, $startAt, $endAt, $doctor->clinic_id);
        }

        if ($procedure && $procedure->requires_room && ! $room) {
            return response()->json([
                'message' => 'Потрібен сумісний кабінет для процедури',
            ], 422);
        }

        if ($procedure?->equipment_id) {
            $equipment = $availability->resolveEquipment(
                $equipment ?? $procedure->equipment,
                $procedure,
                $date,
                $startAt,
                $endAt,
                $doctor->clinic_id
            );
        }

        $conflicts = (new ConflictChecker())->evaluate(
            $doctor,
            $date,
            $startAt,
            $endAt,
            $procedure,
            $room,
            $equipment,
            $validated['patient_id'] ?? $appointment->patient_id,
            $appointment->id,
            $assistantId
        );

        if (!empty($conflicts['hard'])) {
            return response()->json([
                'message' => 'Неможливо змінити запис через конфлікти',
                'hard_conflicts' => $conflicts['hard'],
            ], 422);
        }

        if (!empty($conflicts['soft']) && !($validated['allow_soft_conflicts'] ?? false)) {
            return response()->json([
                'message' => 'Виявлено можливі конфлікти',
                'soft_conflicts' => $conflicts['soft'],
            ], 409);
        }

        $appointment->update([
            'clinic_id' => $doctor->clinic_id,
            'doctor_id' => $doctor->id,

            'procedure_id' => $procedure?->id,
            'procedure_step_id' => $procedureStep?->id,
            'room_id' => $room?->id,
            'equipment_id' => $equipment?->id,

            'assistant_id' => $assistantId,

            'patient_id' => $validated['patient_id'] ?? $appointment->patient_id,
            'is_follow_up' => $validated['is_follow_up'] ?? $appointment->is_follow_up,

            'start_at' => $startAt,
            'end_at' => $endAt,

            'status' => $validated['status'] ?? $appointment->status,
            'comment' => $procedureStep
                ? trim(($validated['comment'] ?? $appointment->comment ?? '') . ' [Етап: ' . $procedureStep->name . ']') ?: null
                : ($validated['comment'] ?? $appointment->comment),
        ]);

        $appointment = $appointment->fresh([
            'clinic:id,name',
            'doctor:id,full_name,clinic_id',
            'assistant:id,name,first_name,last_name',
            'patient:id,full_name,phone',
            'procedure:id,name,duration_minutes',
            'procedureStep:id,procedure_id,name,duration_minutes,order',
            'room:id,name',
            'equipment:id,name',
        ]);

        $this->invalidateSlotsCache($previousDoctorId, $previousStartAt, $previousEndAt);
        $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $appointment->end_at);

        return new AppointmentResource($appointment);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'doctor_ids' => ['nullable', 'array'],
            'doctor_ids.*' => ['integer', 'exists:doctors,id'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        $query = Appointment::query()
            ->with(['doctor', 'assistant', 'patient', 'procedure', 'procedureStep', 'room', 'equipment', 'clinic'])
            ->orderBy('start_at');

        // date OR range
        if (!empty($validated['date'])) {
            $query->whereDate('start_at', $validated['date']);
        } else {
            if (!empty($validated['from_date'])) {
                $query->whereDate('start_at', '>=', $validated['from_date']);
            }
            if (!empty($validated['to_date'])) {
                $query->whereDate('start_at', '<=', $validated['to_date']);
            }
        }

        // doctor filters
        if (!empty($validated['doctor_id'])) {
            $query->where('doctor_id', $validated['doctor_id']);
        } elseif (!empty($validated['doctor_ids'])) {
            $query->whereIn('doctor_id', $validated['doctor_ids']);
        }

        // ✅ Access rules priority:
        // super_admin -> all
        // clinic_admin -> own clinics
        // doctor -> own appointments (only if NOT clinic_admin)
        $clinicAdminClinicIds = $user->clinics()
            ->wherePivot('clinic_role', 'clinic_admin')
            ->pluck('clinics.id');

        if (!$user->hasRole('super_admin')) {
            if ($clinicAdminClinicIds->isNotEmpty()) {
                $query->whereIn('clinic_id', $clinicAdminClinicIds);
            } elseif ($user->hasRole('doctor') && $user->doctor?->id) {
                $query->where('doctor_id', $user->doctor->id);
            }
        }

        if (!empty($validated['clinic_id'])) {
            $query->where('clinic_id', $validated['clinic_id']);
        }

        return AppointmentResource::collection($query->get());
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $appointment->doctor)) {
            abort(403, 'У вас немає доступу до скасування цього запису');
        }

        $data = $request->validate([
            'comment' => ['sometimes', 'nullable', 'string'],
        ]);

        $appointment->update([
            'status' => 'cancelled',
            'comment' => $data['comment'] ?? $appointment->comment,
        ]);

        $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $appointment->end_at);

        $startDate = $appointment->start_at instanceof Carbon
            ? $appointment->start_at->copy()->startOfDay()
            : Carbon::parse($appointment->start_at)->startOfDay();

        $waitlistSuggestions = (new WaitlistService())->matchCandidates(
            $appointment->clinic_id,
            $appointment->doctor_id,
            $appointment->procedure_id,
            $startDate->toDateString()
        );

        AppointmentCancelled::dispatch($appointment);

        return response()->json([
            'status' => 'cancelled',
            'appointment' => new AppointmentResource(
                $appointment->fresh(['clinic', 'doctor', 'assistant', 'patient', 'procedure', 'procedureStep', 'room', 'equipment'])
            ),
            'waitlist_suggestions' => $waitlistSuggestions,
        ]);
    }

    private function invalidateSlotsCache(int $doctorId, $startAt, $endAt): void
    {
        $start = $startAt instanceof Carbon ? $startAt->copy()->startOfDay() : Carbon::parse($startAt)->startOfDay();
        $end = $endAt instanceof Carbon ? $endAt->copy()->startOfDay() : Carbon::parse($endAt)->startOfDay();

        $period = CarbonPeriod::create($start, '1 day', $end);

        foreach ($period as $date) {
            AvailabilityService::bumpSlotsCacheVersion($doctorId, $date);
        }
    }
}
