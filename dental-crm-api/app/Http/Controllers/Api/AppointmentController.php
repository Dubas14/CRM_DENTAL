<?php

namespace App\Http\Controllers\Api;

use App\Events\AppointmentCancelled;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Procedure;
use App\Models\ProcedureStep;
use App\Models\Room;
use App\Models\WaitlistEntry;
use App\Services\Access\DoctorAccessService;
use App\Services\Calendar\AvailabilityService;
use App\Services\Calendar\ConflictChecker;
use App\Services\Calendar\WaitlistService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Helpers\DebugLogHelper;

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

        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до створення запису для цього лікаря');
        }

        $availability = new AvailabilityService;

        $procedure = isset($data['procedure_id']) ? Procedure::find($data['procedure_id']) : null;
        $procedureStep = isset($data['procedure_step_id']) ? ProcedureStep::find($data['procedure_step_id']) : null;
        $room = isset($data['room_id']) ? Room::find($data['room_id']) : null;
        $equipment = isset($data['equipment_id']) ? Equipment::find($data['equipment_id']) : null;
        $assistantId = $data['assistant_id'] ?? null;
        $equipmentWasSelected = array_key_exists('equipment_id', $data) && ! empty($data['equipment_id']);

        if ($procedureStep) {
            if ($procedure && $procedureStep->procedure_id !== $procedure->id) {
                return response()->json([
                    'message' => 'Етап не належить вибраній процедурі',
                ], 422);
            }
            $procedure = $procedure ?? $procedureStep->procedure;
        }

        $date = Carbon::parse($data['date'])->startOfDay();
        $startAt = Carbon::parse($data['date'].' '.$data['time']);

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

        // Спочатку визначаємо обладнання, якщо потрібне
        if ($procedure?->equipment_id) {
            $equipment = $availability->resolveEquipment(
                $equipment ?? $procedure->equipment,
                $procedure,
                $date,
                $startAt,
                $endAt,
                $doctor->clinic_id,
                $room // Передаємо кабінет для перевірки сумісності
            );

            // Якщо користувач явно вибрав обладнання, але воно "зникло" через несумісність кабінет↔обладнання
            if ($equipmentWasSelected && ! $equipment) {
                return response()->json([
                    'message' => 'Обране обладнання не доступне у вибраному кабінеті. Привʼяжіть обладнання до кабінету або виберіть інший кабінет/обладнання.',
                    'error' => 'equipment_not_in_room',
                ], 422);
            }
        }

        // Потім визначаємо кабінет з урахуванням обладнання
        if ($procedure && $procedure->requires_room) {
            $room = $availability->resolveRoom($room, $procedure, $date, $startAt, $endAt, $doctor->clinic_id, $equipment);
        }

        if ($procedure && $procedure->requires_room && ! $room) {
            return response()->json([
                'message' => 'Потрібен сумісний кабінет для процедури',
            ], 422);
        }

        // Якщо кабінет визначений, але обладнання ще ні - перевіряємо сумісність
        if ($room && $procedure?->equipment_id && ! $equipment) {
            $equipment = $availability->resolveEquipment(
                $procedure->equipment,
                $procedure,
                $date,
                $startAt,
                $endAt,
                $doctor->clinic_id,
                $room
            );
        }

        // Перевіряємо, чи вже не існує такий запис (захист від double-submit)
        $existingAppointment = Appointment::where('doctor_id', $doctor->id)
            ->where('start_at', $startAt)
            ->where('end_at', $endAt)
            ->where('patient_id', $data['patient_id'] ?? null)
            ->whereIn('status', ['planned', 'confirmed'])
            ->first();
        
        // #region agent log
        DebugLogHelper::write('AppointmentController.php:151', 'Checking for existing appointment (before transaction)', ['doctor_id' => $doctor->id, 'date' => $date->toDateString(), 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'patient_id' => $data['patient_id'] ?? null, 'existing_appointment_id' => $existingAppointment?->id], 'createAppointment', 'A');
        // #endregion
        
        if ($existingAppointment) {
            // #region agent log
            DebugLogHelper::write('AppointmentController.php:158', 'Existing appointment found, returning it', ['existing_appointment_id' => $existingAppointment->id], 'createAppointment', 'A');
            // #endregion
            $existingAppointment->load([
                'clinic:id,name',
                'doctor:id,full_name,clinic_id',
                'assistant:id,name,first_name,last_name',
                'patient:id,full_name,phone',
                'procedure:id,name,duration_minutes',
                'procedureStep:id,procedure_id,name,duration_minutes,order',
                'room:id,name',
                'equipment:id,name',
            ]);
            return (new AppointmentResource($existingAppointment))
                ->response()
                ->setStatusCode(201);
        }

        // Обгортаємо все в транзакцію, щоб уникнути race condition
        // Та перевіряємо конфлікти з SELECT FOR UPDATE для блокування рядків
        $appointment = DB::transaction(function () use (
            $doctor,
            $date,
            $startAt,
            $endAt,
            $procedure,
            $room,
            $equipment,
            $assistantId,
            $procedureStep,
            $data
        ) {
            // Перевіряємо ще раз всередині транзакції (захист від race condition)
            $existingInTransaction = Appointment::where('doctor_id', $doctor->id)
                ->where('start_at', $startAt)
                ->where('end_at', $endAt)
                ->where(function ($q) use ($data) {
                    if ($data['patient_id'] ?? null) {
                        $q->where('patient_id', $data['patient_id']);
                    } else {
                        $q->whereNull('patient_id');
                    }
                })
                ->whereIn('status', ['planned', 'confirmed'])
                ->lockForUpdate()
                ->first();
            
            // #region agent log
            DebugLogHelper::write('AppointmentController.php:190', 'Checking for existing appointment (inside transaction with lock)', ['doctor_id' => $doctor->id, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'existing_in_transaction_id' => $existingInTransaction?->id], 'createAppointment', 'A');
            // #endregion
            
            if ($existingInTransaction) {
                // #region agent log
                DebugLogHelper::write('AppointmentController.php:195', 'Existing appointment found in transaction, throwing exception', ['existing_appointment_id' => $existingInTransaction->id], 'createAppointment', 'A');
                // #endregion
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'message' => 'Запис вже існує',
                        'appointment_id' => $existingInTransaction->id,
                    ], 409)
                );
            }

            // #region agent log
            DebugLogHelper::write('AppointmentController.php:205', 'ConflictChecker evaluate params (inside transaction)', ['doctor_id' => $doctor->id, 'date' => $date->toDateString(), 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'procedure_id' => $procedure?->id, 'room_id' => $room?->id, 'equipment_id' => $equipment?->id, 'assistant_id' => $assistantId, 'patient_id' => $data['patient_id'] ?? null], 'createAppointment', 'A');
            // #endregion

            $conflicts = (new ConflictChecker)->evaluate(
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

            // #region agent log
            DebugLogHelper::write('AppointmentController.php:175', 'ConflictChecker result (inside transaction)', ['hard_conflicts' => $conflicts['hard'] ?? [], 'soft_conflicts' => $conflicts['soft'] ?? [], 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString(), 'room_id' => $room?->id, 'assistant_id' => $assistantId], 'createAppointment', 'A');
            // #endregion

            if (! empty($conflicts['hard'])) {
                // #region agent log
                DebugLogHelper::write('AppointmentController.php:180', 'Rejecting appointment due to hard conflicts', ['hard_conflicts' => $conflicts['hard']], 'createAppointment', 'A');
                // #endregion
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'message' => 'Неможливо створити запис через конфлікти',
                        'hard_conflicts' => $conflicts['hard'],
                    ], 422)
                );
            }

            // Informational soft conflicts should NOT block creation.
            // We keep blocking only for actionable soft conflicts like patient_busy, missing_prepayment, etc.
            $informationalSoftCodes = ['peak_hours', 'consecutive_appointments'];
            $blockingSoft = array_values(array_filter(
                $conflicts['soft'] ?? [],
                fn ($c) => ! in_array(($c['code'] ?? null), $informationalSoftCodes, true)
            ));

            if (! empty($blockingSoft) && ! ($data['allow_soft_conflicts'] ?? false)) {
                // #region agent log
                DebugLogHelper::write('AppointmentController.php:194', 'Rejecting appointment due to soft conflicts', ['soft_conflicts' => $blockingSoft], 'createAppointment', 'A');
                // #endregion
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'message' => 'Виявлено можливі конфлікти',
                        'soft_conflicts' => $blockingSoft,
                    ], 409)
                );
            }

            // Генеруємо токен для підтвердження запису пацієнтом
            $confirmationToken = Str::random(64);

            // #region agent log
            DebugLogHelper::write('AppointmentController.php:207', 'Creating appointment (inside transaction)', ['doctor_id' => $doctor->id, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString()], 'createAppointment', 'A');
            // #endregion

            $appointment = Appointment::create([
                'clinic_id' => $doctor->clinic_id,
                'doctor_id' => $doctor->id,

                'procedure_id' => $procedure?->id,
                'procedure_step_id' => $procedureStep?->id,
                'room_id' => $room?->id,
                'equipment_id' => $equipment?->id,

                'assistant_id' => $assistantId,
                'patient_id' => $data['patient_id'] ?? null,
                'confirmation_token' => $confirmationToken,

                'is_follow_up' => (bool) ($data['is_follow_up'] ?? false),

                'start_at' => $startAt,
                'end_at' => $endAt,

                'status' => 'planned',
                'source' => $data['source'] ?? 'crm',
                'comment' => $procedureStep
                    ? trim(($data['comment'] ?? '').' [Етап: '.$procedureStep->name.']') ?: null
                    : ($data['comment'] ?? null),
            ]);

            // #region agent log
            DebugLogHelper::write('AppointmentController.php:235', 'Appointment created successfully (inside transaction)', ['appointment_id' => $appointment->id, 'doctor_id' => $doctor->id, 'start_at' => $startAt->toDateTimeString(), 'end_at' => $endAt->toDateTimeString()], 'createAppointment', 'A');
            // #endregion

            return $appointment;
        });

        // #region agent log
        DebugLogHelper::write('AppointmentController.php:238', 'Appointment created, processing post-creation tasks', ['appointment_id' => $appointment->id, 'doctor_id' => $appointment->doctor_id, 'start_at' => $appointment->start_at->toDateTimeString(), 'end_at' => $appointment->end_at->toDateTimeString()], 'createAppointment', 'A');
        // #endregion

        if (! empty($data['waitlist_entry_id'])) {
            WaitlistEntry::where('id', $data['waitlist_entry_id'])->update(['status' => 'booked']);
        }

        $appointment->load([
            'clinic:id,name',
            'doctor:id,full_name,clinic_id',
            'assistant:id,name,first_name,last_name',
            'patient:id,full_name,phone',
            'procedure:id,name,duration_minutes',
            'procedureStep:id,procedure_id,name,duration_minutes,order',
            'room:id,name',
            'equipment:id,name',
        ]);

        // Інвалідуємо кеш слотів для лікаря цього запису
        $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $appointment->end_at);
        
        // Якщо запис використовує кабінет/асистента, інвалідуємо кеш для інших лікарів, які можуть використовувати ці ресурси
        // Це критично, бо getSlots перевіряє доступність кабінетів/асистентів для всіх лікарів
        if ($room) {
            // Отримуємо всіх лікарів, які мали записи в цьому кабінеті на цю дату (для інвалідації їх кешу)
            $doctorsWithRoomAppointments = Appointment::where('room_id', $room->id)
                ->whereDate('start_at', $date)
                ->distinct()
                ->pluck('doctor_id');
            
            foreach ($doctorsWithRoomAppointments as $otherDoctorId) {
                if ($otherDoctorId !== $doctor->id) {
                    $this->invalidateSlotsCache($otherDoctorId, $startAt, $endAt);
                }
            }
        }
        
        if ($assistantId) {
            // Отримуємо всіх лікарів, які мали записи з цим асистентом на цю дату (для інвалідації їх кешу)
            $doctorsWithAssistantAppointments = Appointment::where('assistant_id', $assistantId)
                ->whereDate('start_at', $date)
                ->distinct()
                ->pluck('doctor_id');
            
            foreach ($doctorsWithAssistantAppointments as $otherDoctorId) {
                if ($otherDoctorId !== $doctor->id) {
                    $this->invalidateSlotsCache($otherDoctorId, $startAt, $endAt);
                }
            }
        }

        // #region agent log
        DebugLogHelper::write('AppointmentController.php:273', 'Returning successful response', ['appointment_id' => $appointment->id, 'status_code' => 201], 'createAppointment', 'A');
        // #endregion

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

        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
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

        $availability = new AvailabilityService;

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
            $startAt = Carbon::parse($stepItem['date'].' '.$stepItem['time']);

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

            // Спочатку визначаємо обладнання
            $resolvedEquipment = $equipment;
            if ($procedure->equipment_id) {
                $resolvedEquipment = $availability->resolveEquipment(
                    $resolvedEquipment ?? $procedure->equipment,
                    $procedure,
                    $date,
                    $startAt,
                    $endAt,
                    $doctor->clinic_id,
                    $room // Передаємо кабінет для перевірки сумісності
                );
            }

            // Потім визначаємо кабінет з урахуванням обладнання
            $resolvedRoom = $room;
            if ($procedure->requires_room) {
                $resolvedRoom = $availability->resolveRoom(
                    $resolvedRoom,
                    $procedure,
                    $date,
                    $startAt,
                    $endAt,
                    $doctor->clinic_id,
                    $resolvedEquipment // Передаємо обладнання для перевірки сумісності
                );
            }

            // Якщо кабінет визначений, але обладнання ще ні - перевіряємо сумісність
            if ($resolvedRoom && $procedure->equipment_id && ! $resolvedEquipment) {
                $resolvedEquipment = $availability->resolveEquipment(
                    $procedure->equipment,
                    $procedure,
                    $date,
                    $startAt,
                    $endAt,
                    $doctor->clinic_id,
                    $resolvedRoom
                );
            }

            $conflicts = (new ConflictChecker)->evaluate(
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

            if (! empty($conflicts['hard'])) {
                $conflictsSummary['hard'][] = [
                    'procedure_step_id' => $step->id,
                    'conflicts' => $conflicts['hard'],
                ];
            }

            if (! empty($conflicts['soft'])) {
                $conflictsSummary['soft'][] = [
                    'procedure_step_id' => $step->id,
                    'conflicts' => $conflicts['soft'],
                ];
            }

            $comment = $data['comment'] ?? '';
            $comment = trim($comment.' [Етап: '.$step->name.']') ?: null;

            $appointmentsPayload[] = [
                'clinic_id' => $doctor->clinic_id,
                'doctor_id' => $doctor->id,
                'procedure_id' => $procedure->id,
                'procedure_step_id' => $step->id,
                'room_id' => $resolvedRoom?->id,
                'equipment_id' => $resolvedEquipment?->id,
                'assistant_id' => $assistantId,
                'patient_id' => $data['patient_id'] ?? null,
                'is_follow_up' => (bool) ($data['is_follow_up'] ?? false),
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'planned',
                'source' => $data['source'] ?? 'crm',
                'comment' => $comment,
            ];
        }

        if (! empty($conflictsSummary['hard'])) {
            return response()->json([
                'message' => 'Неможливо створити серію через конфлікти',
                'hard_conflicts' => $conflictsSummary['hard'],
            ], 422);
        }

        // Informational soft conflicts should NOT block series creation.
        $informationalSoftCodes = ['peak_hours', 'consecutive_appointments'];
        $blockingSoftSummary = array_values(array_filter($conflictsSummary['soft'] ?? [], function ($entry) use ($informationalSoftCodes) {
            $conflicts = $entry['conflicts'] ?? [];
            $blocking = array_values(array_filter(
                $conflicts,
                fn ($c) => ! in_array(($c['code'] ?? null), $informationalSoftCodes, true)
            ));

            return ! empty($blocking);
        }));

        if (! empty($blockingSoftSummary) && ! ($data['allow_soft_conflicts'] ?? false)) {
            return response()->json([
                'message' => 'Виявлено можливі конфлікти',
                'soft_conflicts' => $blockingSoftSummary,
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

        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду записів цього лікаря');
        }

        $date = $request->query('date'); // YYYY-MM-DD

        $query = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereNotIn('status', ['cancelled', 'no_show']) // Виключаємо скасовані записи та записи з "не з'явився" зі сторінки розкладу
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
            'start_at' => ['sometimes', 'nullable', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after:start_at'],

            'procedure_id' => ['sometimes', 'nullable', 'exists:procedures,id'],
            'procedure_step_id' => ['sometimes', 'nullable', 'exists:procedure_steps,id'],
            'room_id' => ['sometimes', 'nullable', 'exists:rooms,id'],
            'equipment_id' => ['sometimes', 'nullable', 'exists:equipments,id'],
            'assistant_id' => ['sometimes', 'nullable', 'exists:users,id'],

            'patient_id' => ['sometimes', 'nullable', 'exists:patients,id'],
            'is_follow_up' => ['sometimes', 'boolean'],

            'status' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', Appointment::ALLOWED_STATUSES)],
            'comment' => ['sometimes', 'nullable', 'string'],
            'allow_soft_conflicts' => ['sometimes', 'boolean'],
        ]);

        $doctor = isset($validated['doctor_id'])
            ? Doctor::findOrFail($validated['doctor_id'])
            : $appointment->doctor;

        $user = $request->user();

        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
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
        $equipmentWasSelected = array_key_exists('equipment_id', $validated) && ! empty($validated['equipment_id']);

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
            $startAt = Carbon::parse($dateValue.' '.$timeValue);
        }

        $availability = new AvailabilityService;
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

        // Спочатку визначаємо обладнання, якщо потрібне
        if ($procedure?->equipment_id) {
            $equipment = $availability->resolveEquipment(
                $equipment ?? $procedure->equipment,
                $procedure,
                $date,
                $startAt,
                $endAt,
                $doctor->clinic_id,
                $room // Передаємо кабінет для перевірки сумісності
            );

            // Якщо користувач явно вибрав обладнання, але воно "зникло" через несумісність кабінет↔обладнання
            if ($equipmentWasSelected && ! $equipment) {
                return response()->json([
                    'message' => 'Обране обладнання не доступне у вибраному кабінеті. Привʼяжіть обладнання до кабінету або виберіть інший кабінет/обладнання.',
                    'error' => 'equipment_not_in_room',
                ], 422);
            }
        }

        // Потім визначаємо кабінет з урахуванням обладнання
        if ($procedure && $procedure->requires_room) {
            $room = $availability->resolveRoom($room, $procedure, $date, $startAt, $endAt, $doctor->clinic_id, $equipment);
        }

        if ($procedure && $procedure->requires_room && ! $room) {
            return response()->json([
                'message' => 'Потрібен сумісний кабінет для процедури',
            ], 422);
        }

        // Якщо кабінет визначений, але обладнання ще ні - перевіряємо сумісність
        if ($room && $procedure?->equipment_id && ! $equipment) {
            $equipment = $availability->resolveEquipment(
                $procedure->equipment,
                $procedure,
                $date,
                $startAt,
                $endAt,
                $doctor->clinic_id,
                $room
            );
        }

        $conflicts = (new ConflictChecker)->evaluate(
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

        if (! empty($conflicts['hard'])) {
            return response()->json([
                'message' => 'Неможливо змінити запис через конфлікти',
                'hard_conflicts' => $conflicts['hard'],
            ], 422);
        }

        // Informational soft conflicts should NOT block update.
        $informationalSoftCodes = ['peak_hours', 'consecutive_appointments'];
        $blockingSoft = array_values(array_filter(
            $conflicts['soft'] ?? [],
            fn ($c) => ! in_array(($c['code'] ?? null), $informationalSoftCodes, true)
        ));

        if (! empty($blockingSoft) && ! ($validated['allow_soft_conflicts'] ?? false)) {
            return response()->json([
                'message' => 'Виявлено можливі конфлікти',
                'soft_conflicts' => $blockingSoft,
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
                ? trim(($validated['comment'] ?? $appointment->comment ?? '').' [Етап: '.$procedureStep->name.']') ?: null
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
            'procedure_id' => ['nullable', 'integer', 'exists:procedures,id'],
        ]);

        $query = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show']) // Виключаємо скасовані записи та записи з "не з'явився" з календаря
            ->with(['doctor', 'assistant', 'patient', 'procedure', 'procedureStep', 'room', 'equipment', 'clinic'])
            ->orderBy('start_at');

        // date OR range
        if (! empty($validated['date'])) {
            $query->whereDate('start_at', $validated['date']);
        } else {
            if (! empty($validated['from_date'])) {
                $query->whereDate('start_at', '>=', $validated['from_date']);
            }
            if (! empty($validated['to_date'])) {
                $query->whereDate('start_at', '<=', $validated['to_date']);
            }
        }

        // doctor filters
        if (! empty($validated['doctor_id'])) {
            $query->where('doctor_id', $validated['doctor_id']);
        } elseif (! empty($validated['doctor_ids'])) {
            $query->whereIn('doctor_id', $validated['doctor_ids']);
        }

        // ✅ Access rules priority:
        // super_admin -> all
        // clinic_admin -> own clinics
        // doctor -> own appointments (only if NOT clinic_admin)
        $clinicAdminClinicIds = $user->clinics()
            ->wherePivot('clinic_role', 'clinic_admin')
            ->pluck('clinics.id');

        if (! $user->hasRole('super_admin')) {
            if ($clinicAdminClinicIds->isNotEmpty()) {
                $query->whereIn('clinic_id', $clinicAdminClinicIds);
            } elseif ($user->hasRole('doctor') && $user->doctor?->id) {
                $query->where('doctor_id', $user->doctor->id);
            }
        }

        if (! empty($validated['clinic_id'])) {
            $query->where('clinic_id', $validated['clinic_id']);
        }

        if (! empty($validated['procedure_id'])) {
            $query->where('procedure_id', $validated['procedure_id']);
        }

        // Для календаря потрібні ВСІ записи без пагінації
        // Якщо передано параметр no_pagination=true або для календаря (відсутність пагінації за запитом)
        $noPagination = $request->boolean('no_pagination', false);
        if ($noPagination) {
            return AppointmentResource::collection($query->get());
        }

        // пагінація для інших випадків
        $perPage = $request->integer('per_page', 50);
        $perPage = min(max($perPage, 1), 200);

        return AppointmentResource::collection($query->paginate($perPage));
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        if (! DoctorAccessService::canManageAppointments($user, $appointment->doctor)) {
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

        $waitlistSuggestions = (new WaitlistService)->matchCandidates(
            $appointment->clinic_id,
            $appointment->doctor_id,
            $appointment->procedure_id,
            $startDate
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

    /**
     * Завершити прийом раніше (варіант A): ставимо статус "done" і скорочуємо end_at до поточного часу.
     * Це одразу "повертає" слот для подальших записів.
     */
    public function finish(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        if (! DoctorAccessService::canManageAppointments($user, $appointment->doctor)) {
            abort(403, 'У вас немає доступу до завершення цього запису');
        }

        $data = $request->validate([
            // optional backdated finish time (defaults to now)
            'ended_at' => ['sometimes', 'nullable', 'date'],
        ]);

        // If record hasn't started yet, do not allow finishing early
        $endedAt = ! empty($data['ended_at'])
            ? Carbon::parse($data['ended_at'])
            : now();

        if ($endedAt->lt($appointment->start_at)) {
            return response()->json([
                'message' => 'Цей запис ще не почався. Неможливо завершити прийом раніше початку.',
                'error' => 'appointment_not_started',
            ], 422);
        }

        $previousEndAt = $appointment->end_at;

        // Do not extend the appointment; only shorten it
        if ($previousEndAt && $endedAt->gt($previousEndAt)) {
            $endedAt = $previousEndAt;
        }

        $appointment->update([
            'status' => 'done',
            'end_at' => $endedAt,
        ]);

        // Invalidate slots cache so freed time becomes available immediately
        if ($previousEndAt) {
            $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $previousEndAt);
        }
        $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $appointment->end_at);

        // Перевірка рахунку для пропозиції
        $invoiceSuggestion = $this->getInvoiceSuggestion($appointment);

        return response()->json([
            'status' => 'done',
            'appointment' => new AppointmentResource(
                $appointment->fresh(['clinic', 'doctor', 'assistant', 'patient', 'procedure', 'procedureStep', 'room', 'equipment', 'invoice'])
            ),
            'invoice_suggestion' => $invoiceSuggestion,
        ]);
    }

    /**
     * Отримати пропозицію рахунку для прийому
     */
    private function getInvoiceSuggestion(Appointment $appointment): ?array
    {
        // Якщо вже є рахунок
        if ($appointment->invoice_id) {
            $invoice = Invoice::find($appointment->invoice_id);
            if ($invoice) {
                $debt = (float) $invoice->amount - (float) $invoice->paid_amount;
                if ($debt > 0) {
                    return [
                        'action' => 'pay_existing',
                        'existing_invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'debt_amount' => $debt,
                        'total_amount' => (float) $invoice->amount,
                        'paid_amount' => (float) $invoice->paid_amount,
                    ];
                }

                // Якщо рахунок повністю оплачено - немає пропозиції
                return null;
            }
        }

        // Перевірка наявності авансу (prepayment)
        $prepaymentInvoice = Invoice::where('patient_id', $appointment->patient_id)
            ->where('is_prepayment', true)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
            ->where(function ($q) use ($appointment) {
                if ($appointment->procedure_id) {
                    $q->where('procedure_id', $appointment->procedure_id)
                        ->orWhere('appointment_id', $appointment->id);
                } else {
                    $q->where('appointment_id', $appointment->id);
                }
            })
            ->first();

        if ($prepaymentInvoice) {
            $debt = (float) $prepaymentInvoice->amount - (float) $prepaymentInvoice->paid_amount;
            if ($debt > 0) {
                return [
                    'action' => 'pay_existing',
                    'existing_invoice_id' => $prepaymentInvoice->id,
                    'invoice_number' => $prepaymentInvoice->invoice_number,
                    'debt_amount' => $debt,
                    'total_amount' => (float) $prepaymentInvoice->amount,
                    'paid_amount' => (float) $prepaymentInvoice->paid_amount,
                ];
            }
        }

        // Якщо немає рахунку - пропонуємо створити
        if ($appointment->procedure) {
            return [
                'action' => 'create',
                'procedure_id' => $appointment->procedure_id,
                'procedure_name' => $appointment->procedure->name,
                'procedure_price' => (float) ($appointment->procedure->price ?? 0),
            ];
        }

        return null;
    }

    /**
     * Підтвердження запису пацієнтом через токен
     */
    public function confirm(string $token)
    {
        $appointment = Appointment::where('confirmation_token', $token)
            ->whereIn('status', ['planned', 'confirmed'])
            ->with(['procedure', 'patient'])
            ->first();

        if (! $appointment) {
            return response()->json([
                'message' => 'Токен недійсний або запис вже підтверджено/скасовано',
            ], 404);
        }

        // Перевіряємо, чи запис ще не минув
        if ($appointment->start_at->isPast()) {
            return response()->json([
                'message' => 'Неможливо підтвердити запис, який вже минув',
            ], 422);
        }

        // Перевірка передоплати перед підтвердженням
        if ($appointment->procedure && $appointment->procedure->requires_prepayment) {
            $hasPrepayment = Invoice::where('patient_id', $appointment->patient_id)
                ->where(function ($q) use ($appointment) {
                    $q->where('procedure_id', $appointment->procedure_id)
                        ->orWhere('appointment_id', $appointment->id);
                })
                ->where('status', Invoice::STATUS_PAID)
                ->where('is_prepayment', true)
                ->whereRaw('paid_amount >= amount')
                ->exists();

            if (! $hasPrepayment) {
                return response()->json([
                    'message' => 'Для підтвердження запису потрібна передоплата. Будь ласка, зверніться до клініки.',
                    'requires_prepayment' => true,
                ], 422);
            }
        }

        $appointment->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->invalidateSlotsCache($appointment->doctor_id, $appointment->start_at, $appointment->end_at);

        return response()->json([
            'message' => 'Запис успішно підтверджено',
            'appointment' => new AppointmentResource(
                $appointment->fresh(['clinic', 'doctor', 'assistant', 'patient', 'procedure', 'procedureStep', 'room', 'equipment', 'invoice'])
            ),
        ]);
    }

    /**
     * Перевірка передоплати для запису
     */
    public function checkPrepayment(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        if (! DoctorAccessService::canManageAppointments($user, $appointment->doctor)) {
            abort(403, 'У вас немає доступу до цього запису');
        }

        $hasPrepayment = false;
        $invoice = null;

        if ($appointment->procedure && $appointment->procedure->requires_prepayment) {
            $invoice = Invoice::where('patient_id', $appointment->patient_id)
                ->where(function ($q) use ($appointment) {
                    $q->where('procedure_id', $appointment->procedure_id)
                        ->orWhere('appointment_id', $appointment->id);
                })
                ->where('status', Invoice::STATUS_PAID)
                ->where('is_prepayment', true)
                ->whereRaw('paid_amount >= amount')
                ->first();

            $hasPrepayment = $invoice !== null;

            // Якщо знайдено інвойс, зв'язуємо його з записом
            if ($hasPrepayment && $invoice && ! $appointment->invoice_id) {
                $appointment->update(['invoice_id' => $invoice->id]);
            }
        }

        return response()->json([
            'requires_prepayment' => $appointment->procedure?->requires_prepayment ?? false,
            'has_prepayment' => $hasPrepayment,
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->status,
            ] : null,
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
