<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /**
     * Звіт по записах з фільтрами
     */
    public function appointments(Request $request)
    {
        $request->validate([
            'clinic_id' => ['sometimes', 'nullable', 'exists:clinics,id'],
            'doctor_id' => ['sometimes', 'nullable', 'exists:doctors,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'in:' . implode(',', Appointment::ALLOWED_STATUSES)],
            'format' => ['sometimes', 'nullable', 'string', 'in:json,csv'],
        ]);

        $clinicId = $request->input('clinic_id');
        $doctorId = $request->input('doctor_id');
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->startOfMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now()->endOfMonth();
        $status = $request->input('status');
        $format = $request->input('format', 'json');

        $query = Appointment::query()
            ->with(['doctor:id,full_name', 'patient:id,full_name,phone', 'procedure:id,name', 'room:id,name'])
            ->whereBetween('start_at', [$fromDate, $toDate])
            ->when($clinicId, function ($q) use ($clinicId) {
                $q->whereHas('doctor', fn ($subQ) => $subQ->where('clinic_id', $clinicId));
            })
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('start_at');

        $appointments = $query->get();

        if ($format === 'csv') {
            return $this->exportToCsv($appointments);
        }

        return response()->json([
            'data' => $appointments->map(function ($apt) {
                return [
                    'id' => $apt->id,
                    'start_at' => $apt->start_at->toDateTimeString(),
                    'end_at' => $apt->end_at->toDateTimeString(),
                    'status' => $apt->status,
                    'doctor' => $apt->doctor?->full_name,
                    'patient' => $apt->patient?->full_name,
                    'patient_phone' => $apt->patient?->phone,
                    'procedure' => $apt->procedure?->name,
                    'room' => $apt->room?->name,
                    'comment' => $apt->comment,
                ];
            }),
            'summary' => [
                'total' => $appointments->count(),
                'by_status' => $appointments->groupBy('status')->map->count(),
            ],
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ]);
    }

    /**
     * Експорт в CSV
     */
    private function exportToCsv($appointments)
    {
        $filename = 'appointments_' . Carbon::now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($appointments) {
            $file = fopen('php://output', 'w');
            
            // BOM для правильного відображення кирилиці в Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Заголовки
            fputcsv($file, [
                'ID',
                'Дата початку',
                'Дата завершення',
                'Статус',
                'Лікар',
                'Пацієнт',
                'Телефон',
                'Процедура',
                'Кабінет',
                'Коментар',
            ], ';');

            // Дані
            foreach ($appointments as $apt) {
                fputcsv($file, [
                    $apt->id,
                    $apt->start_at->format('Y-m-d H:i'),
                    $apt->end_at->format('Y-m-d H:i'),
                    $apt->status,
                    $apt->doctor?->full_name ?? '',
                    $apt->patient?->full_name ?? '',
                    $apt->patient?->phone ?? '',
                    $apt->procedure?->name ?? '',
                    $apt->room?->name ?? '',
                    $apt->comment ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

