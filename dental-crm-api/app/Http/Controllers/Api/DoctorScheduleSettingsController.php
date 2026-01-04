<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Services\Calendar\AvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorScheduleSettingsController extends Controller
{
    public function show(Request $request, Doctor $doctor)
    {
        $user = $request->user();
        $clinicId = $request->integer('clinic_id') ?: $doctor->clinic_id;

        $allowedClinicIds = collect([$doctor->clinic_id])
            ->merge($doctor->clinics()->pluck('clinics.id')->all())
            ->filter()
            ->unique()
            ->values()
            ->all();

        // супер-адмін або адмін цієї клініки, або сам лікар
        $canView =
            $user->isSuperAdmin()
            || $user->hasClinicRole($clinicId ?? $doctor->clinic_id, ['clinic_admin'])
            || ($doctor->user_id && $doctor->user_id === $user->id);

        if (! $canView) {
            abort(403, 'Немає доступу до розкладу цього лікаря');
        }

        if ($clinicId && $allowedClinicIds && ! in_array($clinicId, $allowedClinicIds)) {
            abort(422, 'Цей лікар не привʼязаний до вибраної клініки');
        }

        $targetClinicId = $clinicId ?: ($allowedClinicIds[0] ?? null);

        $existing = Schedule::where('doctor_id', $doctor->id)
            ->when($targetClinicId, fn($q) => $q->where('clinic_id', $targetClinicId))
            ->orderBy('weekday')
            ->get()
            ->keyBy('weekday');

        $defaultWorkingDays = $existing->isEmpty();

        $items = collect(range(1, 7))->map(function ($day) use ($doctor, $existing, $defaultWorkingDays, $targetClinicId) {
            $base = [
                'doctor_id' => $doctor->id,
                'clinic_id' => $targetClinicId,
                'weekday'   => $day, // 1=Пн ... 7=Нд
                'is_working' => $defaultWorkingDays && in_array($day, [1,2,3,4,5]),
                'start_time' => '09:00:00',
                'end_time'   => '17:00:00',
                'break_start' => '13:00:00',
                'break_end'   => '14:00:00',
                'slot_duration_minutes' => 30,
            ];

            $schedule = $existing->get($day);

            if (! $schedule) {
                return $base;
            }

            return [
                'doctor_id' => $schedule->doctor_id,
                'clinic_id' => $schedule->clinic_id,
                'weekday'   => $schedule->weekday,
                'is_working'=> true,
                'start_time' => $schedule->start_time,
                'end_time'   => $schedule->end_time,
                'break_start'=> $schedule->break_start,
                'break_end'  => $schedule->break_end,
                'slot_duration_minutes' => $schedule->slot_duration_minutes,
            ];
        });
        return response()->json($items);
    }

    public function update(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        $clinicId = $request->integer('clinic_id') ?: $doctor->clinic_id;
        $allowedClinicIds = collect([$doctor->clinic_id])
            ->merge($doctor->clinics()->pluck('clinics.id')->all())
            ->filter()
            ->unique()
            ->values()
            ->all();

        $canEdit =
            $user->isSuperAdmin()
            || $user->hasClinicRole($clinicId ?? $doctor->clinic_id, ['clinic_admin'])
            || ($doctor->user_id && $doctor->user_id === $user->id);

        if (! $canEdit) {
            abort(403, 'Немає права редагувати розклад цього лікаря');
        }

        $data = $request->validate([
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'days' => ['required', 'array'],
            'days.*.weekday' => ['required', 'integer', 'between:1,7'],
            'days.*.is_working' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time'   => ['nullable', 'date_format:H:i'],
            'days.*.break_start'=> ['nullable', 'date_format:H:i'],
            'days.*.break_end'  => ['nullable', 'date_format:H:i'],
            'days.*.slot_duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ]);

        if ($clinicId && $allowedClinicIds && ! in_array($clinicId, $allowedClinicIds)) {
            abort(422, 'Цей лікар не привʼязаний до вибраної клініки');
        }

        $targetClinicId = $clinicId ?: ($allowedClinicIds[0] ?? null);

        DB::transaction(function () use ($doctor, $data, $targetClinicId) {
            foreach ($data['days'] as $day) {
                if (! $day['is_working']) {
                    // якщо в цей день не працює — просто видаляємо запис
                    Schedule::where('doctor_id', $doctor->id)
                        ->when($targetClinicId, fn($q) => $q->where('clinic_id', $targetClinicId))
                        ->where('weekday', $day['weekday'])
                        ->delete();
                    continue;
                }

                Schedule::updateOrCreate(
                    [
                        'doctor_id' => $doctor->id,
                        'clinic_id' => $targetClinicId,
                        'weekday'   => $day['weekday'],
                    ],
                    [
                        'start_time' => $day['start_time'],
                        'end_time'   => $day['end_time'],
                        'break_start'=> $day['break_start'],
                        'break_end'  => $day['break_end'],
                        'slot_duration_minutes' => $day['slot_duration_minutes'],
                    ]
                );
            }
        });

        // Інвалідовуємо кеш слотів на найближчий період після змін розкладу.
        $this->invalidateSlotsCache($doctor->id, Carbon::today(), Carbon::today()->addDays(30));

        return response()->json(['status' => 'ok']);
    }

    private function invalidateSlotsCache(int $doctorId, Carbon $startAt, Carbon $endAt): void
    {
        $period = CarbonPeriod::create($startAt->copy()->startOfDay(), '1 day', $endAt->copy()->startOfDay());

        foreach ($period as $date) {
            AvailabilityService::bumpSlotsCacheVersion($doctorId, $date);
        }
    }
}
