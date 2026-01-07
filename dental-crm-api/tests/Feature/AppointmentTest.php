<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Clinic $clinic;

    protected Doctor $doctor;

    protected Patient $patient;

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
        $this->patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);
    }

    /**
     * Test can create an appointment.
     */
    public function test_can_create_appointment(): void
    {
        $appointmentData = [
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'date' => now()->addDays(1)->format('Y-m-d'),
            'time' => '10:00',
            'comment' => 'Тестовий запис',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/appointments', $appointmentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'doctor_id',
                'patient_id',
                'start_at',
                'end_at',
                'status',
            ]);

        $this->assertDatabaseHas('appointments', [
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
        ]);
    }

    /**
     * Test cannot create appointment without required fields.
     */
    public function test_cannot_create_appointment_without_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/appointments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['doctor_id', 'date', 'time']);
    }

    /**
     * Test can list appointments.
     */
    public function test_can_list_appointments(): void
    {
        Appointment::factory()->count(3)->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/appointments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'doctor_id',
                    'start_at',
                    'end_at',
                    'status',
                ],
            ]);
    }

    /**
     * Test can filter appointments by date.
     */
    public function test_can_filter_appointments_by_date(): void
    {
        $date = now()->addDays(1);

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'start_at' => $date->setTime(10, 0),
            'end_at' => $date->copy()->setTime(11, 0),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'start_at' => now()->addDays(2)->setTime(10, 0),
            'end_at' => now()->addDays(2)->setTime(11, 0),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/appointments?date='.$date->format('Y-m-d'));

        $response->assertStatus(200);

        $appointments = $response->json();
        $this->assertCount(1, $appointments);
    }

    /**
     * Test can update appointment.
     */
    public function test_can_update_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'status' => 'planned',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/appointments/{$appointment->id}", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }

    /**
     * Test can cancel appointment.
     */
    public function test_can_cancel_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/cancel", [
                'reason' => 'Пацієнт не може прийти',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test can get doctor's appointments.
     */
    public function test_can_get_doctor_appointments(): void
    {
        Appointment::factory()->count(2)->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/doctors/{$this->doctor->id}/appointments");

        $response->assertStatus(200);

        $appointments = $response->json();
        $this->assertCount(2, $appointments);
    }
}
