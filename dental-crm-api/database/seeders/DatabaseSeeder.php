<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\PatientToothStatus;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Супер-адмін для входу в систему
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin'),
            'is_admin' => true,
            'global_role' => 'super_admin',
        ]);

        $clinics = Clinic::factory(3)->create();

        $clinics->each(function (Clinic $clinic, int $index) {
            // Адмін конкретної клініки
            $clinicAdmin = User::factory()->create([
                'name' => 'Clinic ' . ($index + 1) . ' Admin',
                'email' => 'clinic' . ($index + 1) . '@admin.com',
                'password' => Hash::make('admin'),
                'global_role' => 'admin',
            ]);

            $clinic->users()->attach($clinicAdmin->id, ['clinic_role' => 'clinic_admin']);

            // Лікарі з прив'язкою до клініки та роллю
            $doctors = collect();
            foreach (range(1, 3) as $doctorIndex) {
                $doctorUser = User::factory()->create([
                    'name' => fake()->name(),
                    'email' => fake()->unique()->safeEmail(),
                    'password' => Hash::make('password'),
                    'global_role' => 'doctor',
                ]);

                $doctor = Doctor::factory()
                    ->for($clinic)
                    ->for($doctorUser)
                    ->state([
                        'full_name' => $doctorUser->name,
                    ])
                    ->create();

                $clinic->users()->attach($doctorUser->id, ['clinic_role' => 'doctor']);
                $doctors->push($doctor);
            }

            $patients = Patient::factory(20)
                ->for($clinic)
                ->create();

            $doctors->each(function (Doctor $doctor) {
                foreach (range(1, 5) as $weekday) {
                    Schedule::factory()->for($doctor)->state([
                        'weekday' => $weekday,
                    ])->create();
                }

                ScheduleException::factory()->count(2)->for($doctor)->create();
            });

            $appointments = collect();
            foreach (range(1, 40) as $index) {
                $doctor = $doctors->random();
                $patient = $patients->random();
                $start = Carbon::instance(fake()->dateTimeBetween('-1 month', '+1 month'));
                $duration = Arr::random([30, 45, 60]);

                $appointments->push(Appointment::create([
                    'clinic_id' => $clinic->id,
                    'doctor_id' => $doctor->id,
                    'patient_id' => $patient->id,
                    'start_at' => $start,
                    'end_at' => $start->copy()->addMinutes($duration),
                    'status' => Arr::random(['planned', 'confirmed', 'completed', 'cancelled', 'no_show']),
                    'source' => Arr::random(['phone', 'site', 'in_person']),
                    'comment' => fake()->optional()->sentence(),
                ]));
            }

            $patients->each(function (Patient $patient) use ($doctors, $appointments) {
                foreach (range(1, 3) as $toothIndex) {
                    PatientToothStatus::factory()->create([
                        'patient_id' => $patient->id,
                        'tooth_number' => fake()->unique()->numberBetween(11, 48),
                    ]);
                }

                PatientNote::factory(fake()->numberBetween(1, 3))->create([
                    'patient_id' => $patient->id,
                ]);

                $patientAppointments = $appointments->where('patient_id', $patient->id);
                foreach (range(1, fake()->numberBetween(1, 2)) as $recordIndex) {
                    $appointment = $patientAppointments->isNotEmpty()
                        ? $patientAppointments->random()
                        : null;

                    MedicalRecord::factory()->create([
                        'patient_id' => $patient->id,
                        'doctor_id' => $appointment?->doctor_id ?? $doctors->random()->id,
                        'appointment_id' => $appointment?->id,
                        'tooth_number' => Arr::random([null, fake()->numberBetween(11, 48)]),
                    ]);
                }

                fake()->unique(true); // reset unique state per patient
            });
        });
    }
}
