<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::factory()->create();
        $this->user = User::factory()->create();
        $this->user->assignRole('super_admin');

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_patients(): void
    {
        Patient::factory()->count(5)->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->getJson('/api/patients');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'full_name',
                    'phone',
                    'email',
                    'clinic_id',
                ],
            ],
        ]);
    }

    /** @test */
    public function it_can_create_patient(): void
    {
        $patientData = [
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Іван Петренко',
            'phone' => '+380501234567',
            'email' => 'ivan@example.com',
            'birth_date' => '1990-05-15',
            'address' => 'вул. Шевченка, 10',
            'note' => 'Алергія на анестезію',
        ];

        $response = $this->postJson('/api/patients', $patientData);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'full_name' => 'Іван Петренко',
            'phone' => '+380501234567',
        ]);

        $this->assertDatabaseHas('patients', [
            'full_name' => 'Іван Петренко',
            'clinic_id' => $this->clinic->id,
        ]);
    }

    /** @test */
    public function it_requires_clinic_id_and_full_name(): void
    {
        $response = $this->postJson('/api/patients', [
            'phone' => '+380501234567',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['clinic_id', 'full_name']);
    }

    /** @test */
    public function it_validates_email_format(): void
    {
        $response = $this->postJson('/api/patients', [
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Іван Петренко',
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_can_show_patient_details(): void
    {
        $patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Марія Коваленко',
        ]);

        $response = $this->getJson("/api/patients/{$patient->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $patient->id,
            'full_name' => 'Марія Коваленко',
        ]);
    }

    /** @test */
    public function it_can_update_patient(): void
    {
        $patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Старе Імʼя',
        ]);

        $response = $this->putJson("/api/patients/{$patient->id}", [
            'full_name' => 'Нове Імʼя',
            'phone' => '+380509999999',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'full_name' => 'Нове Імʼя',
            'phone' => '+380509999999',
        ]);
    }

    /** @test */
    public function it_can_delete_patient(): void
    {
        $patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->deleteJson("/api/patients/{$patient->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    /** @test */
    public function it_can_search_patients_by_name(): void
    {
        Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Іван Петренко',
        ]);

        Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Марія Шевченко',
        ]);

        $response = $this->getJson('/api/patients?search=Петренко');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['full_name' => 'Іван Петренко']);
    }

    /** @test */
    public function it_can_add_note_to_patient(): void
    {
        $patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->postJson("/api/patients/{$patient->id}/notes", [
            'note' => 'Пацієнт звернувся з болем у зубі',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('patient_notes', [
            'patient_id' => $patient->id,
            'note' => 'Пацієнт звернувся з болем у зубі',
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_patients(): void
    {
        auth()->logout();

        $response = $this->getJson('/api/patients');

        $response->assertStatus(401);
    }
}
