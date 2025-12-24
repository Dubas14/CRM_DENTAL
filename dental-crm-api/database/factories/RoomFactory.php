<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => 'Кабінет ' . fake()->numberBetween(1, 12),
            'is_active' => fake()->boolean(95),
            'equipment' => fake()->optional()->sentence(3),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
