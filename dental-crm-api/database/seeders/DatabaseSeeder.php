<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\PatientToothStatus;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\RoleHierarchy;
use Illuminate\Support\Arr;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        RoleHierarchy::ensureRolesExist();

        // Супер-адмін для входу в систему
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin'),
        ]);
        $superAdmin->assignRole('super_admin');

        $clinics = Clinic::factory(fake()->numberBetween(4, 5))->create();

        $procedureTemplates = [
            ['name' => 'Первинна консультація', 'category' => 'Діагностика', 'duration' => 30],
            ['name' => 'Професійна чистка', 'category' => 'Профілактика', 'duration' => 60],
            ['name' => 'Пломбування зуба', 'category' => 'Терапія', 'duration' => 45],
            ['name' => 'Видалення зуба', 'category' => 'Хірургія', 'duration' => 60],
            ['name' => 'Лікування каналів', 'category' => 'Терапія', 'duration' => 90],
            ['name' => 'Встановлення коронки', 'category' => 'Ортопедія', 'duration' => 90],
            ['name' => 'Відбілювання', 'category' => 'Естетика', 'duration' => 60],
            ['name' => 'Ортодонтичний огляд', 'category' => 'Ортодонтія', 'duration' => 45],
            ['name' => 'Імплантація', 'category' => 'Хірургія', 'duration' => 120],
            ['name' => 'Реставрація зуба', 'category' => 'Терапія', 'duration' => 60],
        ];

        $clinics->each(function (Clinic $clinic, int $index) {
            // Адмін конкретної клініки
            $clinicAdmin = User::factory()->create([
                'name' => 'Clinic ' . ($index + 1) . ' Admin',
                'email' => 'clinic' . ($index + 1) . '@admin.com',
                'password' => Hash::make('admin'),
            ]);
            $clinicAdmin->assignRole('clinic_admin');

            $clinic->users()->attach($clinicAdmin->id, ['clinic_role' => 'clinic_admin']);

            $assistants = collect();
            foreach (range(1, fake()->numberBetween(5, 6)) as $assistantIndex) {
                $assistantUser = User::factory()->create([
                    'name' => fake()->name(),
                    'email' => fake()->unique()->safeEmail(),
                    'password' => Hash::make('password'),
                ]);
                $assistantUser->assignRole('assistant');

                $clinic->users()->attach($assistantUser->id, ['clinic_role' => 'assistant']);
                $assistants->push($assistantUser);
            }

            // Лікарі з прив'язкою до клініки та роллю
            $doctors = collect();
            foreach (range(1, fake()->numberBetween(5, 6)) as $doctorIndex) {
                $doctorUser = User::factory()->create([
                    'name' => fake()->name(),
                    'email' => fake()->unique()->safeEmail(),
                    'password' => Hash::make('password'),
                ]);
                $doctorUser->assignRole('doctor');

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

            $rooms = collect();
            foreach (range(1, fake()->numberBetween(3, 5)) as $roomIndex) {
                $rooms->push(Room::factory()->for($clinic)->create([
                    'name' => 'Кабінет ' . $roomIndex,
                ]));
            }

            $equipments = Equipment::factory(fake()->numberBetween(4, 6))
                ->for($clinic)
                ->create();

            $procedures = collect();
            $procedureCount = fake()->numberBetween(6, 8);
            $procedureNames = collect($procedureTemplates)->shuffle()->take($procedureCount);
            foreach ($procedureNames as $procedureData) {
                $requiresRoom = fake()->boolean(80);
                $requiresAssistant = fake()->boolean(65);
                $procedure = Procedure::factory()->for($clinic)->create([
                    'name' => $procedureData['name'],
                    'category' => $procedureData['category'],
                    'duration_minutes' => $procedureData['duration'],
                    'requires_room' => $requiresRoom,
                    'requires_assistant' => $requiresAssistant,
                    'default_room_id' => $requiresRoom ? $rooms->random()->id : null,
                    'equipment_id' => $equipments->random()->id,
                    'metadata' => [
                        'price_uah' => fake()->numberBetween(600, 4500),
                        'notes' => fake()->optional()->sentence(),
                    ],
                ]);
                $procedures->push($procedure);
            }

            foreach (range(1, 7) as $weekday) {
                $isWorking = $weekday < 7;
                ClinicWorkingHour::factory()->for($clinic)->create([
                    'weekday' => $weekday,
                    'is_working' => $isWorking,
                    'start_time' => $isWorking ? '09:00:00' : null,
                    'end_time' => $isWorking ? '18:00:00' : null,
                    'break_start' => $isWorking ? '13:00:00' : null,
                    'break_end' => $isWorking ? '14:00:00' : null,
                ]);
            }

            $patients = Patient::factory(25)
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
            foreach (range(1, fake()->numberBetween(45, 60)) as $index) {
                $doctor = $doctors->random();
                $patient = $patients->random();
                $procedure = $procedures->random();
                $assistant = $procedure->requires_assistant ? $assistants->random() : fake()->optional()->randomElement($assistants->all());
                $room = $procedure->requires_room ? $rooms->random() : null;
                $start = Carbon::instance(fake()->dateTimeBetween('-1 month', '+1 month'));
                $duration = Arr::random([30, 45, 60]);

                $appointments->push(Appointment::create([
                    'clinic_id' => $clinic->id,
                    'doctor_id' => $doctor->id,
                    'assistant_id' => $assistant?->id,
                    'procedure_id' => $procedure->id,
                    'room_id' => $room?->id,
                    'equipment_id' => $procedure->equipment_id,
                    'patient_id' => $patient->id,
                    'start_at' => $start,
                    'end_at' => $start->copy()->addMinutes($duration),
                    'status' => Arr::random(['planned', 'confirmed', 'completed', 'cancelled', 'no_show']),
                    'source' => Arr::random(['phone', 'site', 'in_person']),
                    'comment' => fake()->optional()->sentence(),
                    'is_follow_up' => fake()->boolean(20),
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

            WaitlistEntry::factory(fake()->numberBetween(6, 10))
                ->state(fn () => [
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patients->random()->id,
                    'doctor_id' => fake()->optional()->randomElement($doctors->all())?->id,
                    'procedure_id' => fake()->optional()->randomElement($procedures->all())?->id,
                ])
                ->create();
        });
    }
}
