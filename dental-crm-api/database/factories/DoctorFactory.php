<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'specialization' => Arr::random([
                'Стоматолог-терапевт',
                'Стоматолог-ортопед',
                'Стоматолог-хірург',
                'Ортодонт',
            ]),
            'status' => Arr::random(['active', 'vacation', 'inactive']),
            'color' => fake()->hexColor(),
            'bio' => fake()->paragraph(),
            'is_active' => true,
        ];
    }
}
