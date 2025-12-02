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
        $validated = $request->validate([
            'doctor_id'      => 'required|exists:doctors,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'tooth_number'   => 'nullable|integer|min:11|max:85',
            'diagnosis'      => 'required|string',
            'treatment'      => 'required|string',
            'complaints'     => 'nullable|string',
            // Опціонально: оновлення статусу зуба разом із записом
            'update_tooth_status' => 'nullable|string' // наприклад: 'filled'
        ]);

        $record = $patient->medicalRecords()->create($validated);

        // Якщо вказано змінити статус зуба (наприклад, полікували карієс -> пломба)
        if ($request->tooth_number && $request->update_tooth_status) {
            PatientToothStatus::updateOrCreate(
                ['patient_id' => $patient->id, 'tooth_number' => $request->tooth_number],
                ['status' => $request->update_tooth_status]
            );
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
