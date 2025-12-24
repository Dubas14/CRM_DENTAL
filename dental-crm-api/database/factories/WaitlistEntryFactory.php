<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'procedure_id' => Procedure::factory(),
            'preferred_date' => fake()->optional()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'status' => Arr::random(['pending', 'proposed', 'booked', 'cancelled']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
