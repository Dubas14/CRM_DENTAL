<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    protected $model = MedicalRecord::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'appointment_id' => Appointment::factory(),
            'tooth_number' => fake()->numberBetween(11, 48),
            'diagnosis' => fake()->randomElement([
                'Карієс',
                'Пульпіт',
                'Періодонтит',
                'Ортодонтичне лікування',
            ]),
            'complaints' => fake()->sentence(),
            'treatment' => fake()->paragraph(),
        ];
    }
}
