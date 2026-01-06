<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
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
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'specialization' => $this->specialization,
            'phone' => $this->phone,
            'email' => $this->email,
            'color' => $this->color,
            'is_active' => (bool) $this->is_active,
            'default_slot_duration' => $this->default_slot_duration,
            'avatar_url' => $this->avatar_url,
            
            'clinic' => $this->whenLoaded('clinic', fn () => new ClinicResource($this->clinic)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
