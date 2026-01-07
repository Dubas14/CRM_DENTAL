<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Room;
use App\Services\Calendar\AvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class FutureAppointmentsSeeder extends Seeder
{
    /**
     * Генерує записи на 6 місяців вперед для всіх лікарів та клінік,
     * використовуючи наявний розклад/слоти, щоб не ламати слоти.
     */
    public function run(): void
    {
        $availability = new AvailabilityService;

        // Мінімальний пул пацієнтів/процедур
        $patients = Patient::all();
        if ($patients->isEmpty()) {
            $patients = Patient::factory(50)->create();
        }
        $procedures = Procedure::all();
        if ($procedures->isEmpty()) {
            $procedures = Procedure::factory(10)->create();
        }

        $startDate = Carbon::today();
        $endDate = $startDate->copy()->addMonths(6);
        $period = new CarbonPeriod($startDate, $endDate);

        Doctor::with(['clinics', 'appointments'])
            ->chunk(25, function ($doctors) use ($availability, $period, $patients, $procedures) {
                foreach ($doctors as $doctor) {
                    $clinicIds = collect([$doctor->clinic_id])
                        ->merge($doctor->clinics?->pluck('id') ?? [])
                        ->filter()
                        ->unique()
                        ->values();

                    if ($clinicIds->isEmpty()) {
                        continue;
                    }

                    foreach ($period as $date) {
                        foreach ($clinicIds as $clinicId) {
                            // План на день з урахуванням клініки
                            $plan = $availability->getDailyPlan($doctor, $date, $clinicId);
                            if (isset($plan['reason'])) {
                                continue;
                            }

                            $slots = $availability->getSlots(
                                $doctor,
                                $date,
                                $plan['slot_duration'] ?? 30,
                                null,
                                null,
                                null,
                                null,
                                $clinicId
                            )['slots'] ?? [];

                            if (empty($slots)) {
                                continue;
                            }

                            // Максимум 2 записи на день, випадкові слоти
                            $daySlots = collect($slots)->shuffle()->take(rand(1, 2));

                            foreach ($daySlots as $slot) {
                                $startAt = Carbon::parse($date->toDateString().' '.$slot['start']);
                                $endAt = Carbon::parse($date->toDateString().' '.$slot['end']);

                                // Пропускаємо минулі слоти (для сьогодні)
                                if ($startAt->isToday() && $endAt->isPast()) {
                                    continue;
                                }

                                // Уникаємо дублювання
                                if (
                                    Appointment::where('doctor_id', $doctor->id)
                                        ->where('start_at', $startAt)
                                        ->exists()
                                ) {
                                    continue;
                                }

                                $patient = $patients->random();
                                // Процедура для цієї клініки або будь-яка
                                $procedure = $procedures
                                    ->where('clinic_id', $clinicId)
                                    ->whenEmpty(fn ($c) => $procedures)
                                    ->random();

                                $room = Room::where('clinic_id', $clinicId)->inRandomOrder()->first();

                                Appointment::create([
                                    'clinic_id' => $clinicId,
                                    'doctor_id' => $doctor->id,
                                    'patient_id' => $patient->id,
                                    'procedure_id' => $procedure?->id,
                                    'room_id' => $room?->id,
                                    'equipment_id' => $procedure?->equipment_id,
                                    'assistant_id' => null,
                                    'start_at' => $startAt,
                                    'end_at' => $endAt,
                                    'status' => 'planned',
                                    'source' => 'seed',
                                    'comment' => 'Seeded future appointment',
                                    'is_follow_up' => false,
                                ]);
                            }
                        }
                    }
                }
            });
    }
}
