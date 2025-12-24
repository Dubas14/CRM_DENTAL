<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    public const ALLOWED_STATUSES = [
        'planned',
        'confirmed',
        'reminded',
        'waiting',
        'done',
        'cancelled',
        'no_show',
    ];

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'procedure_id',
        'procedure_step_id',
        'room_id',
        'assistant_id',
        'equipment_id',
        'patient_id',
        'is_follow_up',
        'start_at',
        'end_at',
        'status',
        'source',
        'comment',
    ];
    protected $appends = [
        'patient_name',
    ];

    protected $casts = [
        'is_follow_up' => 'boolean',
        'start_at'     => 'datetime',
        'end_at'       => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function procedureStep()
    {
        return $this->belongsTo(ProcedureStep::class);
    }
    public function assistant()
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }


    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
    public function getPatientNameAttribute(): ?string
    {
        return $this->patient?->full_name;
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
