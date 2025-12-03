<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientToothStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<PatientToothStatus>
 */
class PatientToothStatusFactory extends Factory
{
    protected $model = PatientToothStatus::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'tooth_number' => fake()->numberBetween(11, 48),
            'status' => Arr::random([
                'healthy', 'caries', 'pulpitis', 'periodontitis',
                'extracted', 'implant', 'crown', 'filled',
            ]),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
