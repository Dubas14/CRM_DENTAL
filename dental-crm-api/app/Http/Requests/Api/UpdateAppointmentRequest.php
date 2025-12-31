<?php

namespace App\Http\Requests\Api;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => ['sometimes', 'exists:doctors,id'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'date_format:H:i'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],

            'procedure_id' => ['sometimes', 'nullable', 'exists:procedures,id'],
            'procedure_step_id' => ['sometimes', 'nullable', 'exists:procedure_steps,id'],
            'room_id' => ['sometimes', 'nullable', 'exists:rooms,id'],
            'equipment_id' => ['sometimes', 'nullable', 'exists:equipments,id'],
            'assistant_id' => ['sometimes', 'nullable', 'exists:users,id'],

            'patient_id' => ['sometimes', 'nullable', 'exists:patients,id'],
            'is_follow_up' => ['sometimes', 'boolean'],

            'status' => ['sometimes', 'string', 'in:' . implode(',', Appointment::ALLOWED_STATUSES)],
            'comment' => ['sometimes', 'nullable', 'string'],
            'allow_soft_conflicts' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_id.exists' => 'Обраного лікаря не знайдено',
            'date.date' => 'Некоректний формат дати',
            'time.date_format' => 'Некоректний формат часу (очікується HH:MM)',
            'start_at.date' => 'Некоректний формат дати початку',
            'end_at.date' => 'Некоректний формат дати завершення',
            'end_at.after' => 'Час завершення має бути після часу початку',
            'status.in' => 'Некоректний статус запису',
        ];
    }
}
