<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

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

    public function getAppointmentsAttribute($value): Collection
    {
        return $this->loadRelationCollection('appointments', fn () => $this->appointments()->get());
    }

    public function getCalendarBlocksAttribute($value): Collection
    {
        return $this->loadRelationCollection('calendarBlocks', fn () => $this->calendarBlocks()->get());
    }

    public function getBlocksAttribute($value): Collection
    {
        $blocks = $this->loadRelationCollection('calendarBlocks', fn () => $this->calendarBlocks()->get());
        $this->setRelation('blocks', $blocks);

        return $blocks;
    }

    /* =======================
     | Helpers
     ======================= */

    public function isActive(): bool
    {
        return $this->is_active === true && $this->status === 'active';
    }

    private function loadRelationCollection(string $relation, callable $loader): Collection
    {
        $loaded = $this->getRelation($relation);
        if ($loaded instanceof Collection) {
            return $loaded;
        }

        $collection = $loader();
        $this->setRelation($relation, $collection);

        return $collection;
    }
}
