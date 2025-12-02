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
        // ... (початок методу той самий: пошук лікаря, валідація) ...
        $user = $request->user();
        $doctor = \App\Models\Doctor::where('user_id', $user->id)->first();

        // Валідація
        $validated = $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id', // <-- Важливо
            'tooth_number'   => 'nullable|integer|min:11|max:85',
            'diagnosis'      => 'required|string',
            'treatment'      => 'required|string',
            'complaints'     => 'nullable|string',
            'update_tooth_status' => 'nullable|string'
        ]);

        $data = $validated;
        $data['doctor_id'] = $doctor ? $doctor->id : $request->doctor_id; // Фолбек

        // 1. Створюємо медичний запис
        $record = $patient->medicalRecords()->create($data);

        // 2. Оновлюємо зуби (якщо треба)
        if (!empty($request->tooth_number) && !empty($request->update_tooth_status)) {
            PatientToothStatus::updateOrCreate(
                ['patient_id' => $patient->id, 'tooth_number' => $request->tooth_number],
                ['status' => $request->update_tooth_status]
            );
        }

        // 3. 🔥 АВТОМАТИЧНО ЗАКРИВАЄМО ВІЗИТ У КАЛЕНДАРІ
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
