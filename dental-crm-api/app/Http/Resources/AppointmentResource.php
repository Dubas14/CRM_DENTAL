<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'doctor_id' => $this->doctor_id,
            'assistant_id' => $this->assistant_id,
            'patient_id' => $this->patient_id,
            'procedure_id' => $this->procedure_id,
            'room_id' => $this->room_id,
            'equipment_id' => $this->equipment_id,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'status' => $this->status,
            'source' => $this->source,
            'comment' => $this->comment,
            'is_follow_up' => $this->is_follow_up,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'doctor' => $this->whenLoaded('doctor'),
            'assistant' => $this->whenLoaded('assistant'),
            'patient' => $this->whenLoaded('patient'),
            'procedure' => $this->whenLoaded('procedure'),
            'room' => $this->whenLoaded('room'),
            'equipment' => $this->whenLoaded('equipment'),
        ];
    }
}
