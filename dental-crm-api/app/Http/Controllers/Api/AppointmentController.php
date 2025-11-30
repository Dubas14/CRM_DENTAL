<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\Access\DoctorAccessService;

class AppointmentController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'date'      => ['required', 'date'],
            'time'      => ['required', 'date_format:H:i'],
            'patient_id'=> ['nullable', 'exists:patients,id'],
            'source'    => ['nullable', 'string', 'max:50'],
            'comment'   => ['nullable', 'string'],
        ]);

        $doctor = Doctor::findOrFail($data['doctor_id']);

        if (!DoctorAccessService::canManageAppointments($user, $doctor)) {
            abort(403, 'У вас немає доступу до створення запису для цього лікаря');
        }

        $date = Carbon::parse($data['date'])->startOfDay();
        $startAt = Carbon::parse($data['date'].' '.$data['time']);

        // тривалість беремо з розкладу (або 30 хв за замовчуванням)
        $schedule = $doctor->schedules()
            ->where('weekday', $date->isoWeekday())
            ->first();

        $slotDuration = $schedule?->slot_duration_minutes ?? 30;
        $endAt = $startAt->copy()->addMinutes($slotDuration);

        // перевірка конфліктів із вже існуючими записами
        $hasConflict = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('start_at', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Цей слот вже зайнятий',
            ], 422);
        }

        $appointment = Appointment::create([
            'clinic_id' => $doctor->clinic_id,
            'doctor_id' => $doctor->id,
            'patient_id'=> null, // додамо, коли буде модуль пацієнтів
            'start_at'  => $startAt,
            'end_at'    => $endAt,
            'status'    => 'planned',
            'source'    => $data['source'] ?? 'crm',
            'comment'   => $data['comment'] ?? null,
        ]);

        return response()->json($appointment, 201);
    }
    public function doctorAppointments(Request $request, Doctor $doctor)
    {
        $this->authorize('view', $doctor); // якщо поки немає policy — тимчасово можна прибрати

        $date = $request->query('date'); // 'YYYY-MM-DD'

        $query = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->orderBy('start_at');

        if ($date) {
            $query->whereDate('start_at', $date);
        }

        return $query->get();
    }

}
