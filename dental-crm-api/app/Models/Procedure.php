<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'category',
        'duration_minutes',
        'requires_room',
        'requires_assistant',
        'default_room_id',
        'equipment_id',
        'metadata',
    ];

    protected $casts = [
        'requires_room' => 'boolean',
        'requires_assistant' => 'boolean',
        'metadata' => 'array',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function defaultRoom()
    {
        return $this->belongsTo(Room::class, 'default_room_id');
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function steps()
    {
        return $this->hasMany(ProcedureStep::class)->orderBy('order');
    }

    public function doctors()
    {
        return $this->belongsToMany(Doctor::class)
            ->withPivot('custom_duration_minutes');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'procedure_room');
    }
}
