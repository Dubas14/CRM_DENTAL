<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
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
            'clinic_id' => ['sometimes', 'exists:clinics,id'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
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
            'clinic_id.exists' => 'Обрану клініку не знайдено',
            'full_name.max' => 'ПІБ не може бути довшим за 255 символів',
            'birth_date.date' => 'Некоректний формат дати народження',
            'birth_date.before' => 'Дата народження має бути в минулому',
            'email.email' => 'Некоректний формат email',
            'phone.max' => 'Телефон не може бути довшим за 50 символів',
        ];
    }
}
