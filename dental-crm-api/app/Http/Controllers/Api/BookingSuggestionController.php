<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\Procedure;
use App\Models\Room;
use App\Services\Access\DoctorAccessService;
use App\Services\Calendar\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'from_date' => ['required', 'date'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
            'preferred_time_of_day' => ['nullable', 'in:morning,afternoon,evening'],
        ]);

        $doctor = Doctor::findOrFail($validated['doctor_id']);
        $user = $request->user();

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду слотів цього лікаря');
        }

        $fromDate = Carbon::parse($validated['from_date'])->startOfDay();

        $procedure = isset($validated['procedure_id']) ? Procedure::find($validated['procedure_id']) : null;
        $room = isset($validated['room_id']) ? Room::find($validated['room_id']) : null;
        $equipment = isset($validated['equipment_id']) ? Equipment::find($validated['equipment_id']) : null;
        $assistantId = $validated['assistant_id'] ?? null;

        $availability = new AvailabilityService();
        $plan = $availability->getDailyPlan($doctor, $fromDate);

        $duration = $validated['duration_minutes'] ?? $availability->resolveProcedureDuration(
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
            $validated['preferred_time_of_day'] ?? null,
            $assistantId
        );

        return response()->json([
            'from_date' => $fromDate->toDateString(),
            'doctor' => $doctor,
            'slots' => $slots,
            'duration_minutes' => $duration,
            'procedure' => $procedure,
            'room' => $room,
            'equipment' => $resolvedEquipment,
            'assistant_id' => $assistantId,
            'preferred_time_of_day' => $validated['preferred_time_of_day'] ?? null,
        ]);
    }
}
