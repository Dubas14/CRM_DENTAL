<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'specialization' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'default_slot_duration' => ['nullable', 'integer', 'min:5', 'max:120'],

            // User credentials (if creating user account)
            'create_user' => ['sometimes', 'boolean'],
            'password' => ['required_if:create_user,true', 'string', 'min:8'],
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
            'full_name.required' => 'ПІБ лікаря є обов\'язковим',
            'full_name.max' => 'ПІБ не може бути довшим за 255 символів',
            'specialization.required' => 'Спеціалізація є обов\'язковою',
            'email.email' => 'Некоректний формат email',
            'email.unique' => 'Користувач з таким email вже існує',
            'color.regex' => 'Колір має бути у форматі HEX (#RRGGBB)',
            'password.required_if' => 'Пароль є обов\'язковим при створенні облікового запису',
            'password.min' => 'Пароль має містити мінімум 8 символів',
        ];
    }
}
