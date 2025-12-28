<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RescheduleCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'appointment_id',
        'patient_id',
        'old_start_at',
        'old_end_at',
        'suggested_slots',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'old_start_at' => 'datetime',
        'old_end_at' => 'datetime',
        'suggested_slots' => 'array',
        'notified_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
