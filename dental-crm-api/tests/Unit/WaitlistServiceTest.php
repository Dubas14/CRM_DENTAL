<?php

namespace Tests\Unit;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\WaitlistEntry;
use App\Services\Calendar\WaitlistService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistServiceTest extends TestCase
{
    use RefreshDatabase;

    private WaitlistService $service;

    private Clinic $clinic;

    private Doctor $doctor;

    private Procedure $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WaitlistService;
        $this->clinic = Clinic::factory()->create();
        $this->doctor = Doctor::factory()->create(['clinic_id' => $this->clinic->id]);
        $this->procedure = Procedure::factory()->create(['clinic_id' => $this->clinic->id]);
    }

    /** @test */
    public function it_matches_candidates_by_clinic(): void
    {
        // Create waitlist entries in different clinics
        $patient1 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);

        $otherClinic = Clinic::factory()->create();
        $patient3 = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'status' => 'pending',
        ]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'status' => 'pending',
        ]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $patient3->id,
            'status' => 'pending',
        ]);

        $candidates = $this->service->matchCandidates($this->clinic->id);

        $this->assertCount(2, $candidates, 'Should match only candidates from the specified clinic');
    }

    /** @test */
    public function it_matches_candidates_by_doctor(): void
    {
        $patient1 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);

        // Entry specifically for this doctor
        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'pending',
        ]);

        // Entry with no doctor preference (should also match)
        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'doctor_id' => null,
            'status' => 'pending',
        ]);

        $candidates = $this->service->matchCandidates(
            $this->clinic->id,
            $this->doctor->id
        );

        $this->assertCount(2, $candidates, 'Should match specific doctor and flexible entries');
    }

    /** @test */
    public function it_matches_candidates_by_procedure(): void
    {
        $patient1 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);

        // Entry specifically for this procedure
        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'procedure_id' => $this->procedure->id,
            'status' => 'pending',
        ]);

        // Entry with no procedure preference (should also match)
        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'procedure_id' => null,
            'status' => 'pending',
        ]);

        $candidates = $this->service->matchCandidates(
            $this->clinic->id,
            null,
            $this->procedure->id
        );

        $this->assertCount(2, $candidates, 'Should match specific procedure and flexible entries');
    }

    /** @test */
    public function it_excludes_non_pending_entries(): void
    {
        $patient1 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'status' => 'pending',
        ]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'status' => 'booked', // Already booked
        ]);

        $candidates = $this->service->matchCandidates($this->clinic->id);

        $this->assertCount(1, $candidates, 'Should only match pending entries');
    }

    /** @test */
    public function it_respects_limit_parameter(): void
    {
        $patients = Patient::factory()->count(10)->create(['clinic_id' => $this->clinic->id]);

        foreach ($patients as $patient) {
            WaitlistEntry::factory()->create([
                'clinic_id' => $this->clinic->id,
                'patient_id' => $patient->id,
                'status' => 'pending',
            ]);
        }

        $candidates = $this->service->matchCandidates($this->clinic->id, null, null, null, 5);

        $this->assertCount(5, $candidates, 'Should respect the limit parameter');
    }

    /** @test */
    public function it_orders_by_preferred_date_when_provided(): void
    {
        $patient1 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);
        $patient3 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'preferred_date' => Carbon::parse('2025-01-10'),
            'status' => 'pending',
            'created_at' => Carbon::parse('2025-01-01'),
        ]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'preferred_date' => Carbon::parse('2025-01-05'),
            'status' => 'pending',
            'created_at' => Carbon::parse('2025-01-02'),
        ]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient3->id,
            'preferred_date' => null, // No preference
            'status' => 'pending',
            'created_at' => Carbon::parse('2025-01-03'),
        ]);

        $candidates = $this->service->matchCandidates(
            $this->clinic->id,
            null,
            null,
            Carbon::parse('2025-01-05')
        );

        // Entries with no preference should come last
        // Then ordered by preferred_date
        $this->assertCount(3, $candidates);
    }

    /** @test */
    public function it_orders_by_creation_date_as_fallback(): void
    {
        $patient1 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $this->clinic->id]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'status' => 'pending',
            'created_at' => Carbon::parse('2025-01-02'),
        ]);

        WaitlistEntry::factory()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'status' => 'pending',
            'created_at' => Carbon::parse('2025-01-01'),
        ]);

        $candidates = $this->service->matchCandidates($this->clinic->id);

        // Earlier created entries should come first
        $this->assertEquals($patient2->id, $candidates->first()->patient_id);
    }
}
