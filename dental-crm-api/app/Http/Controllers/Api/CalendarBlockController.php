<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Services\Access\DoctorAccessService;
use App\Services\Calendar\AvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CalendarBlockController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'type' => ['nullable', 'in:work,vacation,equipment_booking,room_block,personal_block'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = CalendarBlock::query()
            ->where('clinic_id', $validated['clinic_id'])
            ->when($validated['doctor_id'] ?? null, fn ($q, $doctorId) => $q->where('doctor_id', $doctorId))
            ->when($validated['room_id'] ?? null, fn ($q, $roomId) => $q->where('room_id', $roomId))
            ->when($validated['equipment_id'] ?? null, fn ($q, $equipmentId) => $q->where('equipment_id', $equipmentId))
            ->when($validated['assistant_id'] ?? null, fn ($q, $assistantId) => $q->where('assistant_id', $assistantId))
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type));

        if (!empty($validated['from'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $query->where('end_at', '>=', $from);
        }

        if (!empty($validated['to'])) {
            $to = Carbon::parse($validated['to'])->endOfDay();
            $query->where('start_at', '<=', $to);
        }

        return $query->orderBy('start_at')->get();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clinic_id' => ['required', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'in:work,vacation,equipment_booking,room_block,personal_block'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->input('doctor_id')
                && ! $request->input('room_id')
                && ! $request->input('equipment_id')
                && ! $request->input('assistant_id')) {
                $validator->errors()->add('doctor_id', 'Потрібно вказати лікаря, кабінет, обладнання або асистента.');
            }

            $start = Carbon::parse($request->input('start_at'));
            $end = Carbon::parse($request->input('end_at'));

            if ($end->lessThanOrEqualTo($start)) {
                $validator->errors()->add('end_at', 'Час завершення має бути після часу початку.');
            }
        });

        $data = $validator->validate();

        if ($data['doctor_id'] ?? null) {
            $doctor = Doctor::find($data['doctor_id']);
            $this->authorizeDoctorAccess($request, $doctor);
        }

        $block = CalendarBlock::create($data);
        $this->invalidateSlotsCache($block->clinic_id, $block->doctor_id, $block->start_at, $block->end_at);

        return response()->json($block, 201);
    }

    public function show(CalendarBlock $calendarBlock)
    {
        return $calendarBlock;
    }

    public function update(Request $request, CalendarBlock $calendarBlock)
    {
        $validator = Validator::make($request->all(), [
            'clinic_id' => ['sometimes', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'type' => ['sometimes', 'in:work,vacation,equipment_booking,room_block,personal_block'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $validator->after(function ($validator) use ($request, $calendarBlock) {
            $doctorId = $request->input('doctor_id', $calendarBlock->doctor_id);
            $roomId = $request->input('room_id', $calendarBlock->room_id);
            $equipmentId = $request->input('equipment_id', $calendarBlock->equipment_id);
            $assistantId = $request->input('assistant_id', $calendarBlock->assistant_id);

            if (! $doctorId && ! $roomId && ! $equipmentId && ! $assistantId) {
                $validator->errors()->add('doctor_id', 'Потрібно вказати лікаря, кабінет, обладнання або асистента.');
            }

            $start = Carbon::parse($request->input('start_at', $calendarBlock->start_at));
            $end = Carbon::parse($request->input('end_at', $calendarBlock->end_at));

            if ($end->lessThanOrEqualTo($start)) {
                $validator->errors()->add('end_at', 'Час завершення має бути після часу початку.');
            }
        });

        $data = $validator->validate();

        $doctorId = $data['doctor_id'] ?? $calendarBlock->doctor_id;
        if ($doctorId) {
            $doctor = Doctor::find($doctorId);
            $this->authorizeDoctorAccess($request, $doctor);
        }

        $previousClinicId = $calendarBlock->clinic_id;
        $previousDoctorId = $calendarBlock->doctor_id;
        $previousStart = Carbon::parse($calendarBlock->start_at);
        $previousEnd = Carbon::parse($calendarBlock->end_at);

        $calendarBlock->update($data);

        $this->invalidateSlotsCache($previousClinicId, $previousDoctorId, $previousStart, $previousEnd);
        $this->invalidateSlotsCache($calendarBlock->clinic_id, $calendarBlock->doctor_id, $calendarBlock->start_at, $calendarBlock->end_at);

        return $calendarBlock;
    }

    public function destroy(CalendarBlock $calendarBlock)
    {
        $this->invalidateSlotsCache($calendarBlock->clinic_id, $calendarBlock->doctor_id, $calendarBlock->start_at, $calendarBlock->end_at);
        $calendarBlock->delete();

        return response()->noContent();
    }

    private function authorizeDoctorAccess(Request $request, ?Doctor $doctor): void
    {
        if (! $doctor) {
            return;
        }

        $user = $request->user();
        if (! DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до керування календарем цього лікаря');
        }
    }

    private function invalidateSlotsCache(int $clinicId, ?int $doctorId, Carbon $startAt, Carbon $endAt): void
    {
        $doctorIds = $doctorId
            ? [$doctorId]
            : Doctor::query()->where('clinic_id', $clinicId)->pluck('id')->all();

        $period = CarbonPeriod::create($startAt->copy()->startOfDay(), '1 day', $endAt->copy()->startOfDay());

        foreach ($period as $date) {
            foreach ($doctorIds as $id) {
                AvailabilityService::bumpSlotsCacheVersion($id, $date);
            }
        }
    }
}
