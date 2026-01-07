<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'requires_room' => ['sometimes', 'boolean'],
            'requires_assistant' => ['sometimes', 'boolean'],
            'default_room_id' => ['sometimes', 'nullable', 'exists:rooms,id'],
            'equipment_id' => ['sometimes', 'nullable', 'exists:equipments,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'room_ids' => ['sometimes', 'nullable', 'array'],
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
            'name.required' => 'Вкажіть назву процедури.',
            'name.max' => 'Назва не повинна перевищувати 255 символів.',
            'duration_minutes.required' => 'Вкажіть тривалість процедури.',
            'duration_minutes.min' => 'Тривалість має бути не менше 5 хвилин.',
            'duration_minutes.max' => 'Тривалість має бути не більше 480 хвилин (8 годин).',
            'default_room_id.exists' => 'Обраний кабінет не знайдено.',
            'equipment_id.exists' => 'Обране обладнання не знайдено.',
            'room_ids.*.exists' => 'Один з обраних кабінетів не знайдено.',
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
            'name' => 'назва',
            'category' => 'категорія',
            'duration_minutes' => 'тривалість',
            'default_room_id' => 'кабінет за замовчуванням',
            'equipment_id' => 'обладнання',
        ];
    }
}
