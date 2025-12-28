<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'full_name',
        'specialization',
        'status',
        'color',
        'bio',
        'is_active',
    ];

    /* =======================
     | Relations
     ======================= */

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
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
        return $this->is_active === true && $this->status === 'active';
    }

}
