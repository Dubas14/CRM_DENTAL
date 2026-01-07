<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'procedure_id' => $this->procedure_id,
            'preferred_date' => $this->preferred_date,
            'priority' => $this->priority,
            'status' => $this->status,
            'note' => $this->note,

            'patient' => $this->whenLoaded('patient', fn () => new PatientResource($this->patient)),
            'doctor' => $this->whenLoaded('doctor', fn () => new DoctorResource($this->doctor)),
            'procedure' => $this->whenLoaded('procedure', fn () => new ProcedureResource($this->procedure)),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
