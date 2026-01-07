<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'user_id' => null,
            'full_name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-10 years')->format('Y-m-d'),
            'phone' => fake()->unique()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
