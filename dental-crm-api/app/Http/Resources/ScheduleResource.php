<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'weekday' => $this->weekday,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_start' => $this->break_start,
            'break_end' => $this->break_end,
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'doctor' => $this->whenLoaded('doctor'),
        ];
    }
}

