<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => Arr::random([
                'Стоматологічна установка',
                'Рентген-апарат',
                'Автоклав',
                'Лазер для лікування',
                'Ультразвуковий скейлер',
                'Відбілювальна лампа',
            ]),
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90),
        ];
    }
}
