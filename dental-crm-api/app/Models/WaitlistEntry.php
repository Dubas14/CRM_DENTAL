<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'doctor_id',
        'procedure_id',
        'preferred_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'preferred_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
