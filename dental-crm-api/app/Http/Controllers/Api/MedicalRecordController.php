<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientToothStatus;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    // Отримати всю історію пацієнта
    public function index(Patient $patient)
    {
        return $patient->medicalRecords()->with(['doctor.user', 'appointment'])->get();
    }

    // Додати запис в картку
    public function store(Request $request, Patient $patient)
    {
        // Валідація (до речі, тут ми виправили doctor_id)
        $validated = $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id',
            'tooth_number'   => 'nullable|integer|min:11|max:85', // Тут ваша помилка
            'diagnosis'      => 'required|string',
            'treatment'      => 'required|string',
            'complaints'     => 'nullable|string',
            'update_tooth_status' => 'nullable|string'
        ]);

        // 1. Визначаємо лікаря
        $user = $request->user();
        $doctor = \App\Models\Doctor::where('user_id', $user->id)->first();

        $doctorId = null;

        if ($doctor) {
            // Якщо це робить сам лікар
            $doctorId = $doctor->id;
        } elseif (!empty($request->appointment_id)) {
            // Якщо це робить Адмін -> беремо лікаря з візиту
            $appointment = \App\Models\Appointment::find($request->appointment_id);
            $doctorId = $appointment ? $appointment->doctor_id : null;
        }

        // Якщо так і не знайшли лікаря (наприклад, адмін пише нотатку без візиту)
        if (!$doctorId) {
            // Можна видати помилку, або дозволити (якщо запис робить клініка)
            // Поки що кинемо помилку, щоб дані були цілісними
            return response()->json(['message' => 'Неможливо визначити лікаря для цього запису'], 422);
        }

        $data = $validated;
        $data['doctor_id'] = $doctorId;

        // 2. Створюємо запис
        $record = $patient->medicalRecords()->create($data);

        // 3. Оновлюємо статус зуба на карті
        if (!empty($request->tooth_number) && !empty($request->update_tooth_status)) {
            PatientToothStatus::updateOrCreate(
                ['patient_id' => $patient->id, 'tooth_number' => $request->tooth_number],
                ['status' => $request->update_tooth_status]
            );
        }

        // 4. Закриваємо візит (статус "done")
        if (!empty($request->appointment_id)) {
            \App\Models\Appointment::where('id', $request->appointment_id)
                ->update(['status' => 'done']);
        }

        return $record->load('doctor.user');
    }

    // Отримати поточну зубну формулу (стан всіх зубів)
    public function getDentalMap(Patient $patient)
    {
        return $patient->toothStatuses;
    }

    // Оновити статус конкретного зуба (ручна зміна на карті)
    public function updateToothStatus(Request $request, Patient $patient)
    {
        $request->validate([
            'tooth_number' => 'required|integer',
            'status'       => 'required|string'
        ]);

        $status = PatientToothStatus::updateOrCreate(
            ['patient_id' => $patient->id, 'tooth_number' => $request->tooth_number],
            ['status' => $request->status, 'note' => $request->note ?? null]
        );

        return $status;
    }
}
