<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\Access\DoctorAccessService;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Equipment;
use App\Models\WaitlistEntry;
use App\Services\Calendar\AvailabilityService;
use App\Services\Calendar\ConflictChecker;
use App\Services\Calendar\WaitlistService;

class AppointmentController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'date'      => ['required', 'date'],
            'time'      => ['required', 'date_format:H:i'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'room_id'   => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'patient_id'=> ['nullable', 'exists:patients,id'],
            'is_follow_up' => ['boolean'],
            'source'    => ['nullable', 'string', 'max:50'],
            'comment'   => ['nullable', 'string'],
            'waitlist_entry_id' => ['nullable', 'exists:waitlist_entries,id'],
            'allow_soft_conflicts' => ['sometimes', 'boolean'],
        ]);

        $doctor = Doctor::findOrFail($data['doctor_id']);

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до створення запису для цього лікаря');
        }

        $availability = new AvailabilityService();
        $procedure = isset($data['procedure_id']) ? Procedure::find($data['procedure_id']) : null;
        $room = isset($data['room_id']) ? Room::find($data['room_id']) : null;
        $equipment = isset($data['equipment_id']) ? Equipment::find($data['equipment_id']) : null;
        $assistantId = $data['assistant_id'] ?? null;
        $assistant = null;

        $date = Carbon::parse($data['date'])->startOfDay();
        $startAt = Carbon::parse($data['date'].' '.$data['time']);
        $plan = $availability->getDailyPlan($doctor, $date);
        $duration = $procedure?->duration_minutes ?? ($plan['slot_duration'] ?? 30);
        $endAt = $startAt->copy()->addMinutes($duration);

        if ($procedure && $procedure->requires_room) {
            $room = $availability->resolveRoom($room, $procedure, $date, $startAt, $endAt, $doctor->clinic_id);
        }
        if ($procedure && $procedure->requires_assistant) {
            if (!empty($assistantId)) {
                $assistant = User::find($assistantId);
            }
        }

        if ($procedure?->equipment_id) {
            $equipment = $availability->resolveEquipment($equipment ?? $procedure->equipment, $procedure, $date, $startAt, $endAt, $doctor->clinic_id);
        }

        $conflicts = (new ConflictChecker())->evaluate($doctor, $date, $startAt, $endAt, $procedure, $room, $equipment, $data['patient_id'] ?? null, null, $assistant?->id);

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
            'room_id' => $room?->id,
            'equipment_id' => $equipment?->id,
            'assistant_id' => $assistantId,
            'patient_id'=> $data['patient_id'] ?? null,
            'is_follow_up' => $data['is_follow_up'] ?? false,
            'start_at'  => $startAt,
            'end_at'    => $endAt,
            'status'    => 'planned',
            'source'    => $data['source'] ?? 'crm',
            'comment'   => $data['comment'] ?? null,
        ]);

        if (!empty($data['waitlist_entry_id'])) {
            WaitlistEntry::where('id', $data['waitlist_entry_id'])->update(['status' => 'booked']);
        }

        return response()->json($appointment, 201);
    }
    public function doctorAppointments(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду записів цього лікаря');
        }

        $date = $request->query('date'); // 'YYYY-MM-DD'

        $query = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->with(['patient:id,full_name,phone', 'procedure:id,name,duration_minutes', 'room:id,name', 'equipment:id,name'])
            ->orderBy('start_at');

        if ($date) {
            $query->whereDate('start_at', $date);
        }

        return $query->get();
    }
    public function update(Request $request, \App\Models\Appointment $appointment)
    {
        $validated = $request->validate([
            'doctor_id' => ['sometimes', 'exists:doctors,id'],
            'date'      => ['sometimes', 'date'],
            'time'      => ['sometimes', 'date_format:H:i'],
            'procedure_id' => ['sometimes', 'nullable', 'exists:procedures,id'],
            'room_id'   => ['sometimes', 'nullable', 'exists:rooms,id'],
            'equipment_id' => ['sometimes', 'nullable', 'exists:equipments,id'],
            'assistant_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'patient_id' => ['sometimes', 'nullable', 'exists:patients,id'],
            'is_follow_up' => ['sometimes', 'boolean'],
            'status'     => ['sometimes', 'string', 'in:'.implode(',', Appointment::ALLOWED_STATUSES)],
            'comment'    => ['sometimes', 'nullable', 'string'],
            'allow_soft_conflicts' => ['sometimes', 'boolean']
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

        $room = array_key_exists('room_id', $validated)
            ? Room::find($validated['room_id'])
            : $appointment->room;

        $equipment = array_key_exists('equipment_id', $validated)
            ? Equipment::find($validated['equipment_id'])
            : $appointment->equipment;

        $assistantId = array_key_exists('assistant_id', $validated)
            ? $validated['assistant_id']
            : $appointment->assistant_id;
        $assistant = null;

        $dateValue = $validated['date'] ?? $appointment->start_at->toDateString();
        $timeValue = $validated['time'] ?? $appointment->start_at->format('H:i');
        $date = Carbon::parse($dateValue)->startOfDay();
        $startAt = Carbon::parse($dateValue.' '.$timeValue);

        $availability = new AvailabilityService();
        $plan = $availability->getDailyPlan($doctor, $date);
        $duration = $procedure?->duration_minutes ?? ($plan['slot_duration'] ?? 30);
        $endAt = $startAt->copy()->addMinutes($duration);

        if ($procedure && $procedure->requires_room) {
            $room = $availability->resolveRoom($room, $procedure, $date, $startAt, $endAt, $doctor->clinic_id);
        }
        if ($procedure && $procedure->requires_assistant) {
            if (!empty($assistantId)) {
                $assistant = User::find($assistantId);
            }
        }

        if ($procedure?->equipment_id) {
            $equipment = $availability->resolveEquipment($equipment ?? $procedure->equipment, $procedure, $date, $startAt, $endAt, $doctor->clinic_id);
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
            $assistant?->id
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
            'room_id' => $room?->id,
            'equipment_id' => $equipment?->id,
            'assistant_id' => $assistantId,
            'patient_id' => $validated['patient_id'] ?? $appointment->patient_id,
            'is_follow_up' => $validated['is_follow_up'] ?? $appointment->is_follow_up,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $validated['status'] ?? $appointment->status,
            'comment' => $validated['comment'] ?? $appointment->comment,
        ]);

        return $appointment->fresh(['patient', 'doctor', 'procedure', 'room']);
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

        $startDate = $appointment->start_at instanceof Carbon
            ? $appointment->start_at->copy()->startOfDay()
            : Carbon::parse($appointment->start_at)->startOfDay();

        $waitlistSuggestions = (new WaitlistService())->matchCandidates(
            $appointment->clinic_id,
            $appointment->doctor_id,
            $appointment->procedure_id,
            $startDate
        );
        return response()->json([
            'appointment' => $appointment->fresh(['patient', 'doctor', 'procedure', 'room']),
            'waitlist_suggestions' => $waitlistSuggestions,
        ]);
    }

}
