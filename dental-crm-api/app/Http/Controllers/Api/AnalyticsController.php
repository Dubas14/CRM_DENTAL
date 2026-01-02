<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Room;
use App\Models\Procedure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Завантаженість лікарів
     */
    public function doctorsLoad(Request $request)
    {
        $request->validate([
            'clinic_id' => ['sometimes', 'nullable', 'exists:clinics,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $clinicId = $request->input('clinic_id');
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->startOfMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now()->endOfMonth();

        $doctors = Doctor::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where('is_active', true)
            ->with(['appointments' => function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('start_at', [$fromDate, $toDate])
                    ->whereNotIn('status', ['cancelled', 'no_show']);
            }])
            ->get();

        $result = $doctors->map(function ($doctor) use ($fromDate, $toDate) {
            $appointments = $doctor->appointments;
            $totalMinutes = $appointments->sum(function ($apt) {
                return $apt->start_at->diffInMinutes($apt->end_at);
            });

            // Розраховуємо загальний робочий час лікаря за період
            $workingMinutes = 0;
            $current = $fromDate->copy();
            while ($current->lte($toDate)) {
                // Тут можна використати AvailabilityService для точного розрахунку
                // Поки що використовуємо спрощений підхід: 8 годин на день
                $workingMinutes += 8 * 60;
                $current->addDay();
            }

            $loadPercentage = $workingMinutes > 0 ? ($totalMinutes / $workingMinutes) * 100 : 0;

            return [
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->full_name,
                'appointments_count' => $appointments->count(),
                'total_minutes' => $totalMinutes,
                'working_minutes' => $workingMinutes,
                'load_percentage' => round($loadPercentage, 2),
            ];
        });

        return response()->json([
            'data' => $result,
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ]);
    }

    /**
     * Завантаженість кабінетів
     */
    public function roomsLoad(Request $request)
    {
        $request->validate([
            'clinic_id' => ['sometimes', 'nullable', 'exists:clinics,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $clinicId = $request->input('clinic_id');
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->startOfMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now()->endOfMonth();

        $rooms = Room::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where('is_active', true)
            ->with(['appointments' => function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('start_at', [$fromDate, $toDate])
                    ->whereNotIn('status', ['cancelled', 'no_show']);
            }])
            ->get();

        $result = $rooms->map(function ($room) use ($fromDate, $toDate) {
            $appointments = $room->appointments;
            $totalMinutes = $appointments->sum(function ($apt) {
                return $apt->start_at->diffInMinutes($apt->end_at);
            });

            $daysCount = $fromDate->diffInDays($toDate) + 1;
            $workingMinutes = $daysCount * 8 * 60; // 8 годин на день
            $loadPercentage = $workingMinutes > 0 ? ($totalMinutes / $workingMinutes) * 100 : 0;

            return [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'appointments_count' => $appointments->count(),
                'total_minutes' => $totalMinutes,
                'working_minutes' => $workingMinutes,
                'load_percentage' => round($loadPercentage, 2),
            ];
        });

        return response()->json([
            'data' => $result,
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ]);
    }

    /**
     * Популярні процедури
     */
    public function popularProcedures(Request $request)
    {
        $request->validate([
            'clinic_id' => ['sometimes', 'nullable', 'exists:clinics,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $clinicId = $request->input('clinic_id');
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->startOfMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now()->endOfMonth();
        $limit = $request->input('limit', 10);

        $query = Appointment::query()
            ->whereBetween('start_at', [$fromDate, $toDate])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereNotNull('procedure_id')
            ->when($clinicId, function ($q) use ($clinicId) {
                $q->whereHas('doctor', fn ($subQ) => $subQ->where('clinic_id', $clinicId));
            })
            ->select('procedure_id', DB::raw('COUNT(*) as count'))
            ->groupBy('procedure_id')
            ->orderByDesc('count')
            ->limit($limit);

        $results = $query->get()->map(function ($item) {
            $procedure = Procedure::find($item->procedure_id);
            return [
                'procedure_id' => $item->procedure_id,
                'procedure_name' => $procedure?->name ?? 'Unknown',
                'count' => $item->count,
            ];
        });

        return response()->json([
            'data' => $results,
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ]);
    }

    /**
     * Конверсія записів
     */
    public function conversion(Request $request)
    {
        $request->validate([
            'clinic_id' => ['sometimes', 'nullable', 'exists:clinics,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $clinicId = $request->input('clinic_id');
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->startOfMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now()->endOfMonth();

        $query = Appointment::query()
            ->whereBetween('start_at', [$fromDate, $toDate])
            ->when($clinicId, function ($q) use ($clinicId) {
                $q->whereHas('doctor', fn ($subQ) => $subQ->where('clinic_id', $clinicId));
            });

        $total = (clone $query)->count();
        $confirmed = (clone $query)->where('status', 'confirmed')->count();
        $done = (clone $query)->where('status', 'done')->count();
        $cancelled = (clone $query)->where('status', 'cancelled')->count();
        $noShow = (clone $query)->where('status', 'no_show')->count();

        $conversionRate = $total > 0 ? ($done / $total) * 100 : 0;
        $confirmationRate = $total > 0 ? ($confirmed / $total) * 100 : 0;
        $cancellationRate = $total > 0 ? ($cancelled / $total) * 100 : 0;
        $noShowRate = $total > 0 ? ($noShow / $total) * 100 : 0;

        return response()->json([
            'data' => [
                'total' => $total,
                'confirmed' => $confirmed,
                'done' => $done,
                'cancelled' => $cancelled,
                'no_show' => $noShow,
                'conversion_rate' => round($conversionRate, 2),
                'confirmation_rate' => round($confirmationRate, 2),
                'cancellation_rate' => round($cancellationRate, 2),
                'no_show_rate' => round($noShowRate, 2),
            ],
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ]);
    }

    /**
     * No-show rate
     */
    public function noShowRate(Request $request)
    {
        $request->validate([
            'clinic_id' => ['sometimes', 'nullable', 'exists:clinics,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $clinicId = $request->input('clinic_id');
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->startOfMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now()->endOfMonth();

        $query = Appointment::query()
            ->whereBetween('start_at', [$fromDate, $toDate])
            ->whereIn('status', ['confirmed', 'reminded', 'waiting', 'no_show'])
            ->when($clinicId, function ($q) use ($clinicId) {
                $q->whereHas('doctor', fn ($subQ) => $subQ->where('clinic_id', $clinicId));
            });

        $total = (clone $query)->count();
        $noShow = (clone $query)->where('status', 'no_show')->count();

        $noShowRate = $total > 0 ? ($noShow / $total) * 100 : 0;

        return response()->json([
            'data' => [
                'total_appointments' => $total,
                'no_show_count' => $noShow,
                'no_show_rate' => round($noShowRate, 2),
            ],
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ]);
    }
}

