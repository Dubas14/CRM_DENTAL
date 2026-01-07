<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Services\Calendar\AvailabilityService;
use App\Services\Calendar\ConflictChecker;
use App\Services\Calendar\WaitlistService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id ?? $request->query('clinic_id');

        $entries = WaitlistEntry::query()
            ->with(['patient:id,full_name,phone', 'doctor:id,full_name', 'procedure:id,name'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(25);

        return $entries;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'patient_id' => ['required', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'preferred_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $clinic = Clinic::findOrFail($data['clinic_id']);

        $entry = WaitlistEntry::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $data['patient_id'],
            'doctor_id' => $data['doctor_id'] ?? null,
            'procedure_id' => $data['procedure_id'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($entry->load(['patient', 'doctor', 'procedure']), 201);
    }

    public function markBooked(Request $request, WaitlistEntry $waitlistEntry)
    {
        $waitlistEntry->update(['status' => 'booked']);

        return $waitlistEntry->fresh(['patient', 'doctor', 'procedure']);
    }

    public function cancel(Request $request, WaitlistEntry $waitlistEntry)
    {
        $waitlistEntry->update(['status' => 'cancelled']);

        return response()->json(['status' => 'cancelled']);
    }

    public function candidates(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'preferred_date' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
        ]);

        $service = new WaitlistService;

        $preferredDate = isset($data['preferred_date']) ? Carbon::parse($data['preferred_date'])->startOfDay() : null;

        $candidates = $service->matchCandidates(
            $data['clinic_id'],
            $data['doctor_id'] ?? null,
            $data['procedure_id'] ?? null,
            $preferredDate,
            $data['limit'] ?? 5
        );

        return $candidates;
    }

    public function claim(Request $request, string $token)
    {
        $result = DB::transaction(function () use ($token) {
            $offer = WaitlistOffer::where('token', $token)->lockForUpdate()->first();

            if (! $offer) {
                return response()->json(['message' => 'Пропозицію не знайдено'], 404);
            }

            if ($offer->expires_at && $offer->expires_at->isPast()) {
                $offer->update(['status' => 'expired']);

                return response()->json(['message' => 'Пропозиція прострочена'], 410);
            }

            if ($offer->status !== 'pending') {
                return response()->json(['message' => 'Слот вже зайнято'], 409);
            }

            $appointment = Appointment::lockForUpdate()->find($offer->appointment_id);

            if (! $appointment || $appointment->status !== 'cancelled') {
                $offer->update(['status' => 'failed']);

                return response()->json(['message' => 'Слот більше недоступний'], 409);
            }

            $entry = $offer->waitlistEntry()->with('patient')->first();
            $patient = $entry?->patient;

            if (! $patient) {
                $offer->update(['status' => 'failed']);

                return response()->json(['message' => 'Пацієнт недоступний'], 422);
            }

            $doctor = $appointment->doctor;
            $procedure = $appointment->procedure;
            $room = $appointment->room;
            $equipment = $appointment->equipment ?? $procedure?->equipment;
            $assistantId = $appointment->assistant_id;

            $startAt = Carbon::parse($appointment->start_at);
            $endAt = Carbon::parse($appointment->end_at);
            $date = $startAt->copy()->startOfDay();

            $availability = new AvailabilityService;

            // Спочатку визначаємо обладнання, якщо потрібне
            if ($procedure?->equipment_id) {
                $equipment = $availability->resolveEquipment(
                    $equipment ?? $procedure->equipment,
                    $procedure,
                    $date,
                    $startAt,
                    $endAt,
                    $appointment->clinic_id,
                    $room
                );
            }

            // Потім визначаємо кабінет з урахуванням обладнання
            if ($procedure && $procedure->requires_room) {
                $room = $availability->resolveRoom($room, $procedure, $date, $startAt, $endAt, $appointment->clinic_id, $equipment);
            }

            if ($procedure && $procedure->requires_room && ! $room) {
                $offer->update(['status' => 'failed']);

                return response()->json(['message' => 'Немає сумісного кабінету'], 422);
            }

            // Якщо кабінет визначений, але обладнання ще ні - перевіряємо сумісність
            if ($room && $procedure?->equipment_id && ! $equipment) {
                $equipment = $availability->resolveEquipment(
                    $procedure->equipment,
                    $procedure,
                    $date,
                    $startAt,
                    $endAt,
                    $appointment->clinic_id,
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
                $patient->id,
                null,
                $assistantId
            );

            if (! empty($conflicts['hard'])) {
                $offer->update(['status' => 'failed']);

                return response()->json([
                    'message' => 'Слот більше недоступний',
                    'hard_conflicts' => $conflicts['hard'],
                ], 409);
            }

            $newAppointment = Appointment::create([
                'clinic_id' => $appointment->clinic_id,
                'doctor_id' => $appointment->doctor_id,
                'procedure_id' => $appointment->procedure_id,
                'procedure_step_id' => $appointment->procedure_step_id,
                'room_id' => $room?->id,
                'assistant_id' => $assistantId,
                'equipment_id' => $equipment?->id,
                'patient_id' => $patient->id,
                'is_follow_up' => $appointment->is_follow_up,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'planned',
                'source' => 'waitlist',
                'comment' => $appointment->comment,
            ]);

            $offer->update([
                'status' => 'claimed',
                'claimed_at' => now(),
            ]);

            WaitlistOffer::where('appointment_id', $appointment->id)
                ->where('id', '!=', $offer->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            $entry->update(['status' => 'booked']);

            $newAppointment = $newAppointment->fresh([
                'clinic:id,name',
                'doctor:id,full_name,clinic_id',
                'assistant:id,full_name',
                'patient:id,full_name,phone',
                'procedure:id,name,duration_minutes',
                'procedureStep:id,procedure_id,name,duration_minutes,order',
                'room:id,name',
                'equipment:id,name',
            ]);

            return new AppointmentResource($newAppointment);
        });

        return $result;
    }
}
