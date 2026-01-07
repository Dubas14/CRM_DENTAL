<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Services\Calendar\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AvailabilityService $service;

    protected Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AvailabilityService;
        $this->doctor = Doctor::factory()->create();
    }

    /**
     * Test returns empty slots when no schedule exists.
     */
    public function test_returns_empty_slots_when_no_schedule_exists(): void
    {
        $date = Carbon::parse('2025-01-06'); // Monday

        $result = $this->service->getSlots($this->doctor, $date, 30);

        $this->assertEquals([], $result['slots']);
        $this->assertEquals('no_schedule', $result['reason']);
    }

    /**
     * Test returns slots based on schedule.
     */
    public function test_returns_slots_based_on_schedule(): void
    {
        $date = Carbon::parse('2025-01-06'); // Monday

        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => 1, // Monday
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 30,
        ]);

        $result = $this->service->getSlots($this->doctor, $date, 30);

        $this->assertNotEmpty($result['slots']);
        $this->assertCount(6, $result['slots']); // 9:00, 9:30, 10:00, 10:30, 11:00, 11:30
    }

    /**
     * Test excludes lunch break from slots.
     */
    public function test_excludes_lunch_break_from_slots(): void
    {
        $date = Carbon::parse('2025-01-06'); // Monday

        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => 1, // Monday
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00',
            'slot_duration_minutes' => 30,
        ]);

        $result = $this->service->getSlots($this->doctor, $date, 30);

        $this->assertNotEmpty($result['slots']);

        // Check that no slot overlaps with break time
        foreach ($result['slots'] as $slot) {
            $slotTime = Carbon::parse($slot['start'])->format('H:i');
            $this->assertTrue(
                $slotTime < '12:00' || $slotTime >= '13:00',
                "Slot at {$slotTime} should not be during break"
            );
        }
    }

    /**
     * Test excludes booked appointments from available slots.
     */
    public function test_excludes_booked_appointments_from_slots(): void
    {
        $date = Carbon::parse('2025-01-06'); // Monday

        Schedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 30,
        ]);

        // Book an appointment at 10:00
        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'start_at' => $date->copy()->setTime(10, 0),
            'end_at' => $date->copy()->setTime(10, 30),
            'status' => 'confirmed',
        ]);

        $result = $this->service->getSlots($this->doctor, $date, 30);

        $this->assertNotEmpty($result['slots']);

        // Check that 10:00 slot is not available
        $hasBookedSlot = collect($result['slots'])->contains(function ($slot) {
            return Carbon::parse($slot['start'])->format('H:i') === '10:00';
        });

        $this->assertFalse($hasBookedSlot, '10:00 slot should not be available');
    }

    /**
     * Test conflict detection works correctly.
     */
    public function test_conflict_detection_works_correctly(): void
    {
        $appointments = collect([
            (object) ['start_at' => Carbon::parse('2025-01-06 10:00'), 'end_at' => Carbon::parse('2025-01-06 11:00')],
            (object) ['start_at' => Carbon::parse('2025-01-06 14:00'), 'end_at' => Carbon::parse('2025-01-06 15:00')],
        ]);

        // Test overlapping slot
        $hasConflict = $this->service->hasConflict(
            $appointments,
            Carbon::parse('2025-01-06 10:30'),
            Carbon::parse('2025-01-06 11:30')
        );
        $this->assertTrue($hasConflict, 'Should detect conflict with overlapping appointment');

        // Test non-overlapping slot
        $hasConflict = $this->service->hasConflict(
            $appointments,
            Carbon::parse('2025-01-06 12:00'),
            Carbon::parse('2025-01-06 13:00')
        );
        $this->assertFalse($hasConflict, 'Should not detect conflict with non-overlapping slot');
    }

    /**
     * Test resolves procedure duration correctly.
     */
    public function test_resolves_procedure_duration_correctly(): void
    {
        $procedure = (object) ['duration_minutes' => 60];

        // Without custom duration
        $duration = $this->service->resolveProcedureDuration($this->doctor, $procedure, 30);
        $this->assertEquals(60, $duration);

        // With fallback
        $duration = $this->service->resolveProcedureDuration($this->doctor, null, 45);
        $this->assertEquals(45, $duration);
    }
}
