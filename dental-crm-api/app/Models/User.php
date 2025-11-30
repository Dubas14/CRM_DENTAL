<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'global_role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'clinic_user')
            ->withPivot('clinic_role')
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->global_role === 'super_admin';
    }

    public function clinicRole(int $clinicId): ?string
    {
        $clinic = $this->clinics->firstWhere('id', $clinicId);
        return $clinic?->pivot?->clinic_role;
    }

    public function hasClinicRole(int $clinicId, array|string $roles): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $roles = (array) $roles;
        $role = $this->clinicRole($clinicId);

        return $role && in_array($role, $roles, true);
    }
}
