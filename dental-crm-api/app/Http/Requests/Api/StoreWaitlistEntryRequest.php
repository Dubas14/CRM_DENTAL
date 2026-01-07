<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaitlistEntryRequest extends FormRequest
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
            'clinic_id' => ['required', 'exists:clinics,id'],
            'patient_id' => ['required', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
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
            'clinic_id.required' => 'Клініка є обов\'язковою',
            'clinic_id.exists' => 'Обрану клініку не знайдено',
            'patient_id.required' => 'Пацієнт є обов\'язковим',
            'patient_id.exists' => 'Обраного пацієнта не знайдено',
            'doctor_id.exists' => 'Обраного лікаря не знайдено',
            'procedure_id.exists' => 'Обрану процедуру не знайдено',
            'preferred_date.date' => 'Некоректний формат дати',
            'preferred_date.after_or_equal' => 'Бажана дата не може бути в минулому',
            'priority.min' => 'Мінімальний пріоритет - 1',
            'priority.max' => 'Максимальний пріоритет - 10',
        ];
    }
}
