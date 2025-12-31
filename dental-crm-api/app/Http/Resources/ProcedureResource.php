<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProcedureResource extends JsonResource
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
            'name' => $this->name,
            'category' => $this->category,
            'duration_minutes' => $this->duration_minutes,
            'requires_room' => (bool) $this->requires_room,
            'requires_assistant' => (bool) $this->requires_assistant,
            'default_room_id' => $this->default_room_id,
            'equipment_id' => $this->equipment_id,
            'metadata' => $this->metadata,
            
            'steps' => $this->whenLoaded('steps', fn () => ProcedureStepResource::collection($this->steps)),
            'rooms' => $this->whenLoaded('rooms', fn () => RoomResource::collection($this->rooms)),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
