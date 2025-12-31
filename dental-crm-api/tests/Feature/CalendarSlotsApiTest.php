<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarSlotsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Clinic $clinic;
    private Doctor $doctor;
    private Procedure $procedure;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::factory()->create();
        
        $this->user = User::factory()->create();
        $this->user->assignRole('super_admin');

        $this->doctor = Doctor::factory()->create([
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->user->id,
        ]);

        $this->procedure = Procedure::factory()->create([
            'clinic_id' => $this->clinic->id,
            'duration_minutes' => 60,
        ]);

        $this->room = Room::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_get_available_slots_for_doctor(): void
    {
        // Create schedule for tomorrow
        $tomorrow = now()->addDay();
        
        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => $tomorrow->isoWeekday(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration_minutes' => 30,
        ]);

        $response = $this->getJson("/api/doctors/{$this->doctor->id}/slots?date={$tomorrow->toDateString()}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
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

    /** @test */
    public function it_returns_empty_slots_for_day_off(): void
    {
        $tomorrow = now()->addDay();

        // No schedule created - day off

        $response = $this->getJson("/api/doctors/{$this->doctor->id}/slots?date={$tomorrow->toDateString()}");

        $response->assertStatus(200);
        $response->assertJson([
            'slots' => [],
            'reason' => 'no_schedule',
        ]);
    }

    /** @test */
    public function it_can_get_recommended_slots(): void
    {
        $tomorrow = now()->addDay();
        
        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => $tomorrow->isoWeekday(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration_minutes' => 30,
        ]);

        $response = $this->getJson("/api/doctors/{$this->doctor->id}/recommended-slots?procedure_id={$this->procedure->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'slots' => [],
            'duration_minutes',
        ]);
    }

    /** @test */
    public function it_can_get_booking_suggestions(): void
    {
        $tomorrow = now()->addDay();
        
        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => $tomorrow->isoWeekday(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration_minutes' => 30,
        ]);

        $response = $this->getJson("/api/booking-suggestions?doctor_id={$this->doctor->id}&procedure_id={$this->procedure->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'suggestions' => [],
        ]);
    }

    /** @test */
    public function it_requires_date_parameter_for_slots(): void
    {
        $response = $this->getJson("/api/doctors/{$this->doctor->id}/slots");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date']);
    }

    /** @test */
    public function it_validates_date_format(): void
    {
        $response = $this->getJson("/api/doctors/{$this->doctor->id}/slots?date=invalid-date");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date']);
    }

    /** @test */
    public function it_can_get_doctor_schedule_settings(): void
    {
        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => 1, // Monday
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response = $this->getJson("/api/doctors/{$this->doctor->id}/weekly-schedule");

        $response->assertStatus(200);
        $response->assertJsonStructure([
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
    }

    /** @test */
    public function it_can_update_doctor_weekly_schedule(): void
    {
        $scheduleData = [
            'schedules' => [
                [
                    'weekday' => 1,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'break_start' => '13:00',
                    'break_end' => '14:00',
                    'slot_duration_minutes' => 30,
                ],
            ],
        ];

        $response = $this->putJson("/api/doctors/{$this->doctor->id}/weekly-schedule", $scheduleData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('schedules', [
            'doctor_id' => $this->doctor->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_slots(): void
    {
        auth()->logout();

        $response = $this->getJson("/api/doctors/{$this->doctor->id}/slots?date=" . now()->addDay()->toDateString());

        $response->assertStatus(401);
    }
}

