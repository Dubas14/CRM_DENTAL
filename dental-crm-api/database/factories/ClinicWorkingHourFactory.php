<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicWorkingHour>
 */
class ClinicWorkingHourFactory extends Factory
{
    protected $model = ClinicWorkingHour::class;

    public function definition(): array
    {
        $isWorking = fake()->boolean(85);

        return [
            'clinic_id' => Clinic::factory(),
            'weekday' => fake()->numberBetween(1, 7),
            'is_working' => $isWorking,
            'start_time' => $isWorking ? '09:00:00' : null,
            'end_time' => $isWorking ? '18:00:00' : null,
            'break_start' => $isWorking ? '13:00:00' : null,
            'break_end' => $isWorking ? '14:00:00' : null,
        ];
    }
}
