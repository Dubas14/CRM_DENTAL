<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientNote>
 */
class PatientNoteFactory extends Factory
{
    protected $model = PatientNote::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
        ];
    }
}
