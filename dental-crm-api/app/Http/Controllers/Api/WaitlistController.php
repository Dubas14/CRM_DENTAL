<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use App\Services\Calendar\WaitlistService;
use Carbon\Carbon;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id ?? $request->query('clinic_id');

        $entries = WaitlistEntry::query()
            ->with(['patient:id,full_name,phone', 'doctor:id,full_name', 'procedure:id,name'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(25);

        return $entries;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'patient_id' => ['required', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'preferred_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $clinic = Clinic::findOrFail($data['clinic_id']);

        $entry = WaitlistEntry::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $data['patient_id'],
            'doctor_id' => $data['doctor_id'] ?? null,
            'procedure_id' => $data['procedure_id'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($entry->load(['patient', 'doctor', 'procedure']), 201);
    }

    public function markBooked(Request $request, WaitlistEntry $waitlistEntry)
    {
        $waitlistEntry->update(['status' => 'booked']);

        return $waitlistEntry->fresh(['patient', 'doctor', 'procedure']);
    }

    public function cancel(Request $request, WaitlistEntry $waitlistEntry)
    {
        $waitlistEntry->update(['status' => 'cancelled']);

        return response()->json(['status' => 'cancelled']);
    }

    public function candidates(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'preferred_date' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
        ]);

        $service = new WaitlistService();

        $preferredDate = isset($data['preferred_date']) ? Carbon::parse($data['preferred_date'])->startOfDay() : null;

        $candidates = $service->matchCandidates(
            $data['clinic_id'],
            $data['doctor_id'] ?? null,
            $data['procedure_id'] ?? null,
            $preferredDate,
            $data['limit'] ?? 5
        );

        return $candidates;
    }
}
