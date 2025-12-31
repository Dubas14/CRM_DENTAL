<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaitlistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in controller
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
            'preferred_time_start' => ['nullable', 'date_format:H:i'],
            'preferred_time_end' => ['nullable', 'date_format:H:i', 'after:preferred_time_start'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'comment' => ['nullable', 'string', 'max:500'],
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
            'clinic_id.required' => 'Виберіть клініку.',
            'clinic_id.exists' => 'Обрану клініку не знайдено.',
            'patient_id.required' => 'Виберіть пацієнта.',
            'patient_id.exists' => 'Обраного пацієнта не знайдено.',
            'doctor_id.exists' => 'Обраного лікаря не знайдено.',
            'procedure_id.exists' => 'Обрану процедуру не знайдено.',
            'preferred_date.date' => 'Невірний формат дати.',
            'preferred_date.after_or_equal' => 'Дата не може бути в минулому.',
            'preferred_time_start.date_format' => 'Час повинен бути у форматі ГГ:ХХ.',
            'preferred_time_end.date_format' => 'Час повинен бути у форматі ГГ:ХХ.',
            'preferred_time_end.after' => 'Час завершення має бути пізніше часу початку.',
            'priority.min' => 'Пріоритет має бути від 1 до 10.',
            'priority.max' => 'Пріоритет має бути від 1 до 10.',
            'comment.max' => 'Коментар не повинен перевищувати 500 символів.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clinic_id' => 'клініка',
            'patient_id' => 'пацієнт',
            'doctor_id' => 'лікар',
            'procedure_id' => 'процедура',
            'preferred_date' => 'бажана дата',
            'preferred_time_start' => 'бажаний час початку',
            'preferred_time_end' => 'бажаний час завершення',
            'priority' => 'пріоритет',
            'comment' => 'коментар',
        ];
    }
}

