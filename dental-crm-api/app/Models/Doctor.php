<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'full_name',
        'specialization',
        'avatar_path',
        'phone',
        'email',
        'room',
        'admin_contact',
        'address',
        'city',
        'state',
        'zip',
        'status',
        'vacation_from',
        'vacation_to',
        'color',
        'bio',
        'is_active',
    ];

    protected $appends = [
        'avatar_url',
    ];

    /* =======================
     | Relations
     ======================= */

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'doctor_clinic')->withTimestamps();
    }

    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'doctor_specialization');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function procedures(): BelongsToMany
    {
        return $this->belongsToMany(Procedure::class)
            ->withPivot('custom_duration_minutes');
    }

    /**
     * Записи пацієнтів
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Блоки календаря (перерви, особисті блоки, відпустки)
     */
    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class);
    }

    /**
     * Аліас для сумісності зі старим кодом
     */
    public function blocks(): HasMany
    {
        return $this->calendarBlocks();
    }

    /* =======================
     | Helpers
     ======================= */

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        // 1) Прямо з доктора
        if ($this->avatar_path) {
            return asset('storage/'.ltrim($this->avatar_path, '/'));
        }

        // 2) Fallback: аватар користувача (щоб працювало для всіх ролей)
        $userAvatarPath = $this->relationLoaded('user')
            ? $this->user?->avatar_path
            : $this->user()->value('avatar_path');

        if ($userAvatarPath) {
            return asset('storage/'.ltrim($userAvatarPath, '/'));
        }

        return null;
    }

    public function getEmailAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        return $this->relationLoaded('user') ? $this->user?->email : $this->user()->value('email');
    }

    public function isOnVacationAt(?string $date = null): bool
    {
        if (! $this->vacation_from || ! $this->vacation_to) {
            return false;
        }

        $target = $date ?: now()->toDateString();

        return $target >= $this->vacation_from && $target <= $this->vacation_to;
    }
}
