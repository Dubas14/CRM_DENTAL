<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicWorkingHoursController extends Controller
{
    public function show(Request $request, Clinic $clinic)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinic->id, ['clinic_admin'])) {
            abort(403, 'Немає доступу до налаштувань цієї клініки');
        }

        $existing = ClinicWorkingHour::where('clinic_id', $clinic->id)
            ->orderBy('weekday')
            ->get()
            ->keyBy('weekday');

        $defaultWorkingDays = $existing->isEmpty();

        $items = collect(range(1, 7))->map(function ($day) use ($clinic, $existing, $defaultWorkingDays) {
            $base = [
                'clinic_id' => $clinic->id,
                'weekday'   => $day,
                'is_working' => $defaultWorkingDays && in_array($day, [1, 2, 3, 4, 5], true),
                'start_time' => '09:00:00',
                'end_time'   => '18:00:00',
                'break_start' => '13:00:00',
                'break_end'   => '14:00:00',
            ];

            $schedule = $existing->get($day);

            if (! $schedule) {
                return $base;
            }

            return [
                'clinic_id' => $schedule->clinic_id,
                'weekday'   => $schedule->weekday,
                'is_working' => $schedule->is_working,
                'start_time' => $schedule->start_time,
                'end_time'   => $schedule->end_time,
                'break_start' => $schedule->break_start,
                'break_end'   => $schedule->break_end,
            ];
        });

        return response()->json($items);
    }

    public function update(Request $request, Clinic $clinic)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinic->id, ['clinic_admin'])) {
            abort(403, 'Немає права редагувати налаштування цієї клініки');
        }

        $data = $request->validate([
            'days' => ['required', 'array'],
            'days.*.weekday' => ['required', 'integer', 'between:1,7'],
            'days.*.is_working' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time'   => ['nullable', 'date_format:H:i'],
            'days.*.break_start' => ['nullable', 'date_format:H:i'],
            'days.*.break_end' => ['nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($clinic, $data) {
            foreach ($data['days'] as $day) {
                ClinicWorkingHour::updateOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'weekday' => $day['weekday'],
                    ],
                    [
                        'is_working' => (bool) $day['is_working'],
                        'start_time' => $day['is_working'] ? $day['start_time'] : null,
                        'end_time' => $day['is_working'] ? $day['end_time'] : null,
                        'break_start' => $day['is_working'] ? $day['break_start'] : null,
                        'break_end' => $day['is_working'] ? $day['break_end'] : null,
                    ]
                );
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
