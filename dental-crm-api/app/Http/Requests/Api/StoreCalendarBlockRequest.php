<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarBlockRequest extends FormRequest
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
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'in:work,vacation,equipment_booking,room_block,personal_block'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
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
            'doctor_id.exists' => 'Обраного лікаря не знайдено',
            'room_id.exists' => 'Обраний кабінет не знайдено',
            'equipment_id.exists' => 'Обране обладнання не знайдено',
            'assistant_id.exists' => 'Обраного асистента не знайдено',
            'type.required' => 'Тип блокування є обов\'язковим',
            'type.in' => 'Некоректний тип блокування',
            'start_at.required' => 'Час початку є обов\'язковим',
            'start_at.date' => 'Некоректний формат часу початку',
            'end_at.required' => 'Час завершення є обов\'язковим',
            'end_at.date' => 'Некоректний формат часу завершення',
            'end_at.after' => 'Час завершення має бути після часу початку',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->doctor_id && ! $this->room_id && ! $this->equipment_id && ! $this->assistant_id) {
                $validator->errors()->add('doctor_id', 'Потрібно вказати лікаря, кабінет, обладнання або асистента.');
            }
        });
    }
}
