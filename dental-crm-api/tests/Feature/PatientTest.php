<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'super_admin']);

        $this->user = User::factory()->create();
        $this->user->assignRole('super_admin');

        $this->clinic = Clinic::factory()->create();
    }

    /**
     * Test can list patients.
     */
    public function test_can_list_patients(): void
    {
        Patient::factory()->count(3)->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/patients');

        $response->assertStatus(200)
            ->assertJsonStructure([
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

    /**
     * Test can create a patient.
     */
    public function test_can_create_patient(): void
    {
        $patientData = [
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Іван Петренко',
            'phone' => '+380501234567',
            'email' => 'ivan@example.com',
            'birth_date' => '1990-01-15',
            'address' => 'Київ, вул. Хрещатик, 1',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/patients', $patientData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'full_name' => 'Іван Петренко',
                'phone' => '+380501234567',
            ]);

        $this->assertDatabaseHas('patients', [
            'full_name' => 'Іван Петренко',
            'email' => 'ivan@example.com',
        ]);
    }

    /**
     * Test cannot create patient without required fields.
     */
    public function test_cannot_create_patient_without_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/patients', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clinic_id', 'full_name']);
    }

    /**
     * Test can view a patient.
     */
    public function test_can_view_patient(): void
    {
        $patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/patients/{$patient->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $patient->id,
                'full_name' => $patient->full_name,
            ]);
    }

    /**
     * Test can update a patient.
     */
    public function test_can_update_patient(): void
    {
        $patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/patients/{$patient->id}", [
                'full_name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'full_name' => 'New Name',
            ]);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'full_name' => 'New Name',
        ]);
    }

    /**
     * Test can delete a patient.
     */
    public function test_can_delete_patient(): void
    {
        $patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/patients/{$patient->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('patients', [
            'id' => $patient->id,
        ]);
    }

    /**
     * Test can search patients.
     */
    public function test_can_search_patients(): void
    {
        Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Іван Іванов',
            'phone' => '+380501111111',
        ]);

        Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
            'full_name' => 'Петро Петров',
            'phone' => '+380502222222',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/patients?search=Іван');

        $response->assertStatus(200)
            ->assertJsonFragment(['full_name' => 'Іван Іванов'])
            ->assertJsonMissing(['full_name' => 'Петро Петров']);
    }
}
