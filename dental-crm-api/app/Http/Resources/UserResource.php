<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\RoleHierarchy;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'avatar_url' => $this->avatar_url,

            'global_role' => $this->when(
                true,
                fn () => $this->global_role ?? RoleHierarchy::highestRole($this->getRoleNames()->all()) ?? 'user'
            ),
            'roles' => $this->when(
                true,
                fn () => $this->relationLoaded('roles') ? $this->roles : $this->getRoleNames()
            ),
            'permissions' => $this->when(
                true,
                fn () => $this->permissions ?? $this->getAllPermissions()->pluck('name')
            ),

            'doctor' => $this->whenLoaded('doctor', fn () => new DoctorResource($this->doctor)),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

