<?php

namespace App\Http\Requests\Api;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
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
            'doctor_id' => ['required', 'exists:doctors,id'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],

            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'procedure_step_id' => ['nullable', 'exists:procedure_steps,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],

            'patient_id' => ['nullable', 'exists:patients,id'],
            'is_follow_up' => ['sometimes', 'boolean'],

            'source' => ['nullable', 'string', 'max:50'],
            'comment' => ['nullable', 'string'],

            'waitlist_entry_id' => ['nullable', 'exists:waitlist_entries,id'],
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
            'doctor_id.required' => 'Лікар є обов\'язковим',
            'doctor_id.exists' => 'Обраного лікаря не знайдено',
            'date.required' => 'Дата є обов\'язковою',
            'date.date' => 'Некоректний формат дати',
            'time.required' => 'Час є обов\'язковим',
            'time.date_format' => 'Некоректний формат часу (очікується HH:MM)',
            'procedure_id.exists' => 'Обрану процедуру не знайдено',
            'room_id.exists' => 'Обраний кабінет не знайдено',
            'equipment_id.exists' => 'Обране обладнання не знайдено',
            'patient_id.exists' => 'Обраного пацієнта не знайдено',
        ];
    }
}
