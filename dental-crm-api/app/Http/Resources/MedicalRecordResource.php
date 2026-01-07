<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'appointment_id' => $this->appointment_id,
            'tooth_number' => $this->tooth_number,
            'diagnosis' => $this->diagnosis,
            'treatment' => $this->treatment,
            'complaints' => $this->complaints,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'patient' => $this->whenLoaded('patient'),
            'doctor' => $this->whenLoaded('doctor'),
            'appointment' => $this->whenLoaded('appointment'),
        ];
    }
}
