<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'is_active',
        'equipment',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function procedures()
    {
        return $this->belongsToMany(Procedure::class, 'procedure_room');
    }

    public function equipments()
    {
        return $this->belongsToMany(Equipment::class, 'room_equipment');
    }
}
