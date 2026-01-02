<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,

            'clinic_id' => $this->clinic_id,
            'doctor_id' => $this->doctor_id,
            'assistant_id' => $this->assistant_id,
            'patient_id' => $this->patient_id,

            'procedure_id' => $this->procedure_id,
            'procedure_step_id' => $this->procedure_step_id,
            'room_id' => $this->room_id,
            'equipment_id' => $this->equipment_id,

            // IMPORTANT:
            // appointments.start_at/end_at are stored as "dateTime" (without timezone) in DB.
            // Returning Carbon objects serializes to ISO8601 with timezone (Z) which makes the browser shift times,
            // causing UI to show free slots that backend treats as busy.
            // We return plain "Y-m-d H:i:s" strings to keep the app timezone-less and consistent.
            'start_at' => $this->start_at?->format('Y-m-d H:i:s'),
            'end_at' => $this->end_at?->format('Y-m-d H:i:s'),

            'status' => $this->status,
            'source' => $this->source,
            'comment' => $this->comment,
            'is_follow_up' => (bool) $this->is_follow_up,

            // якщо в Appointment є accessor getPatientNameAttribute()
            'patient_name' => $this->when(isset($this->patient_name), $this->patient_name),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'clinic' => $this->whenLoaded('clinic'),
            'doctor' => $this->whenLoaded('doctor'),
            'assistant' => $this->whenLoaded('assistant'),
            'patient' => $this->whenLoaded('patient'),
            'procedure' => $this->whenLoaded('procedure'),
            'procedure_step' => $this->whenLoaded('procedureStep'),
            'room' => $this->whenLoaded('room'),
            'equipment' => $this->whenLoaded('equipment'),
        ];
    }
}
