<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Clinic $clinic;

    private Doctor $doctor;

    private Patient $patient;

    private Procedure $procedure;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->clinic = Clinic::factory()->create();

        $this->user = User::factory()->create();
        $this->user->assignRole('super_admin');

        $this->doctor = Doctor::factory()->create([
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->user->id,
        ]);

        $this->patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $this->procedure = Procedure::factory()->create([
            'clinic_id' => $this->clinic->id,
            'duration_minutes' => 60,
        ]);

        $this->room = Room::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        // Create schedule for doctor
        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => now()->addDay()->isoWeekday(),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'slot_duration_minutes' => 30,
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_create_appointment(): void
    {
        $date = now()->addDay()->toDateString();
        $time = '10:00';

        $response = $this->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'procedure_id' => $this->procedure->id,
            'room_id' => $this->room->id,
            'date' => $date,
            'time' => $time,
            'comment' => 'Test appointment',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'doctor_id',
                'patient_id',
                'procedure_id',
                'room_id',
                'start_at',
                'end_at',
                'status',
                'comment',
            ],
        ]);

        $this->assertDatabaseHas('appointments', [
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'procedure_id' => $this->procedure->id,
            'comment' => 'Test appointment',
        ]);
    }

    /** @test */
    public function it_requires_doctor_id_to_create_appointment(): void
    {
        $response = $this->postJson('/api/appointments', [
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['doctor_id']);
    }

    /** @test */
    public function it_can_update_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'clinic_id' => $this->clinic->id,
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
        ]);

        $response = $this->putJson("/api/appointments/{$appointment->id}", [
            'comment' => 'Updated comment',
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'comment' => 'Updated comment',
            'status' => 'confirmed',
        ]);
    }

    /** @test */
    public function it_can_cancel_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'clinic_id' => $this->clinic->id,
            'status' => 'planned',
        ]);

        $response = $this->postJson("/api/appointments/{$appointment->id}/cancel", [
            'reason' => 'Patient request',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    /** @test */
    public function it_can_list_doctor_appointments(): void
    {
        Appointment::factory()->count(3)->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'start_at' => now()->addDay(),
        ]);

        $response = $this->getJson("/api/doctors/{$this->doctor->id}/appointments");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_can_filter_appointments_by_date(): void
    {
        $targetDate = now()->addDay()->toDateString();

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'start_at' => now()->addDay()->setTime(10, 0),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'start_at' => now()->addDays(2)->setTime(10, 0),
        ]);

        $response = $this->getJson("/api/appointments?date={$targetDate}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function unauthenticated_user_cannot_create_appointment(): void
    {
        Sanctum::actingAs(User::factory()->create()); // Remove auth
        auth()->logout();

        $response = $this->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00',
        ]);

        $response->assertStatus(401);
    }
}
