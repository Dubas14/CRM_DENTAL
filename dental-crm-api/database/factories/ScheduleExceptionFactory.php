<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\ScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<ScheduleException>
 */
class ScheduleExceptionFactory extends Factory
{
    protected $model = ScheduleException::class;

    public function definition(): array
    {
        $type = Arr::random(['day_off', 'override']);

        return [
            'doctor_id' => Doctor::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'type' => $type,
            'start_time' => $type === 'override' ? '10:00:00' : null,
            'end_time' => $type === 'override' ? '15:00:00' : null,
        ];
    }
}
