<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 week', '+1 week');
        $duration = Arr::random([30, 45, 60]);

        return [
            'clinic_id' => Clinic::factory(),
            'doctor_id' => Doctor::factory(),
            'assistant_id' => User::factory(),
            'procedure_id' => Procedure::factory(),
            'room_id' => Room::factory(),
            'equipment_id' => Equipment::factory(),
            'patient_id' => Patient::factory(),
            'start_at' => Carbon::instance($start),
            'end_at' => Carbon::instance($start)->copy()->addMinutes($duration),
            'status' => Arr::random(['planned', 'confirmed', 'completed', 'cancelled']),
            'source' => Arr::random(['phone', 'site', 'in_person']),
            'comment' => fake()->sentence(),
            'is_follow_up' => fake()->boolean(20),
        ];
    }
}
