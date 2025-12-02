<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'full_name',
        'birth_date',
        'phone',
        'email',
        'address',
        'note',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class)->latest();
    }

    public function toothStatuses()
    {
        return $this->hasMany(PatientToothStatus::class);
    }
    public function notes()
    {
        return $this->hasMany(PatientNote::class)->latest(); // Сортуємо: нові зверху
    }
}
