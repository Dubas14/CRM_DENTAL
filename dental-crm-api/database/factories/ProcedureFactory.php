<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Equipment;
use App\Models\Procedure;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => Arr::random([
                'Первинна консультація',
                'Професійна чистка',
                'Пломбування зуба',
                'Видалення зуба',
                'Лікування каналів',
                'Встановлення коронки',
                'Відбілювання',
                'Ортодонтичний огляд',
            ]),
            'category' => Arr::random([
                'Діагностика',
                'Терапія',
                'Хірургія',
                'Ортодонтія',
                'Профілактика',
            ]),
            'duration_minutes' => Arr::random([30, 45, 60, 90]),
            'requires_room' => fake()->boolean(75),
            'requires_assistant' => fake()->boolean(60),
            'default_room_id' => Room::factory(),
            'equipment_id' => Equipment::factory(),
            'price' => fake()->randomFloat(2, 300, 6000),
            'code' => 'A-' . fake()->unique()->numberBetween(100, 999),
            'metadata' => [
                'price_uah' => fake()->numberBetween(600, 4500),
                'notes' => fake()->optional()->sentence(),
            ],
        ];
    }
}
