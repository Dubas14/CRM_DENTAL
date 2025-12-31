<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'requires_room' => ['boolean'],
            'requires_assistant' => ['boolean'],
            'default_room_id' => ['nullable', 'exists:rooms,id'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'metadata' => ['nullable', 'array'],
            'steps' => ['nullable', 'array'],
            'steps.*.name' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.duration_minutes' => ['required_with:steps', 'integer', 'min:5', 'max:480'],
            'steps.*.order' => ['required_with:steps', 'integer', 'min:1'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
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
            'name.required' => 'Назва процедури є обов\'язковою',
            'name.max' => 'Назва процедури не може бути довшою за 255 символів',
            'duration_minutes.required' => 'Тривалість процедури є обов\'язковою',
            'duration_minutes.min' => 'Мінімальна тривалість процедури - 5 хвилин',
            'duration_minutes.max' => 'Максимальна тривалість процедури - 480 хвилин (8 годин)',
            'default_room_id.exists' => 'Обраний кабінет не знайдено',
            'equipment_id.exists' => 'Обране обладнання не знайдено',
        ];
    }
}
