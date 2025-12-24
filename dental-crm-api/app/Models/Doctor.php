<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function schedules()
    {
        return $this->hasMany(\App\Models\Schedule::class);
    }

    public function procedures()
    {
        return $this->belongsToMany(Procedure::class)
            ->withPivot('custom_duration_minutes');
    }
}
