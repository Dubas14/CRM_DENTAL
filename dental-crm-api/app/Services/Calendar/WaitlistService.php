<?php

namespace App\Services\Calendar;

use App\Models\WaitlistEntry;
use Carbon\Carbon;

class WaitlistService
{
    public function matchCandidates(
        int $clinicId,
        ?int $doctorId = null,
        ?int $procedureId = null,
        ?Carbon $preferredDate = null,
        int $limit = 5
    ) {
        return WaitlistEntry::query()
            ->with(['patient:id,full_name,phone', 'doctor:id,full_name', 'procedure:id,name'])
            ->where('clinic_id', $clinicId)
            ->where('status', 'pending')
            ->when($doctorId, fn ($q) => $q->where(function ($inner) use ($doctorId) {
                $inner->whereNull('doctor_id')->orWhere('doctor_id', $doctorId);
            }))
            ->when($procedureId, fn ($q) => $q->where(function ($inner) use ($procedureId) {
                $inner->whereNull('procedure_id')->orWhere('procedure_id', $procedureId);
            }))
            ->when($preferredDate, function ($q) {
                $q->orderByRaw('preferred_date IS NULL')
                    ->orderBy('preferred_date');
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}
