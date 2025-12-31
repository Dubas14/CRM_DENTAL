<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class CalendarSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Clinic $clinic;
    protected Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'super_admin']);

        $this->user = User::factory()->create();
        $this->user->assignRole('super_admin');

        $this->clinic = Clinic::factory()->create();
        $this->doctor = Doctor::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        // Create a weekly schedule for the doctor
        for ($weekday = 1; $weekday <= 5; $weekday++) {
            Schedule::factory()->create([
                'doctor_id' => $this->doctor->id,
                'weekday' => $weekday,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
                'slot_duration_minutes' => 30,
            ]);
        }
    }

    /**
     * Test can get available slots for a doctor.
     */
    public function test_can_get_available_slots(): void
    {
        $date = now()->next('Monday')->format('Y-m-d');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/doctors/{$this->doctor->id}/slots?date={$date}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'slots' => [
                    '*' => [
                        'start',
                        'end',
                    ],
                ],
            ]);

        $slots = $response->json('slots');
        $this->assertNotEmpty($slots);
    }

    /**
     * Test slots exclude lunch break.
     */
    public function test_slots_exclude_lunch_break(): void
    {
        $date = now()->next('Monday')->format('Y-m-d');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/doctors/{$this->doctor->id}/slots?date={$date}");

        $response->assertStatus(200);

        $slots = $response->json('slots');

        // Check that no slots overlap with lunch break (12:00-13:00)
        foreach ($slots as $slot) {
            $startTime = \Carbon\Carbon::parse($slot['start'])->format('H:i');
            $endTime = \Carbon\Carbon::parse($slot['end'])->format('H:i');

            $this->assertTrue(
                $endTime <= '12:00' || $startTime >= '13:00',
                "Slot {$startTime} - {$endTime} overlaps with lunch break"
            );
        }
    }

    /**
     * Test returns no slots on weekend.
     */
    public function test_returns_no_slots_on_weekend(): void
    {
        $date = now()->next('Saturday')->format('Y-m-d');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/doctors/{$this->doctor->id}/slots?date={$date}");

        $response->assertStatus(200)
            ->assertJson([
                'slots' => [],
                'reason' => 'no_schedule',
            ]);
    }

    /**
     * Test can get doctor's schedule.
     */
    public function test_can_get_doctor_schedule(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/doctors/{$this->doctor->id}/schedule");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'schedules' => [
                    '*' => [
                        'weekday',
                        'start_time',
                        'end_time',
                        'break_start',
                        'break_end',
                    ],
                ],
            ]);

        $schedules = $response->json('schedules');
        $this->assertCount(5, $schedules); // Monday to Friday
    }

    /**
     * Test can get recommended slots.
     */
    public function test_can_get_recommended_slots(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/doctors/{$this->doctor->id}/recommended-slots");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'slots',
            ]);
    }
}

