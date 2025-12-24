<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcedureStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'procedure_id',
        'name',
        'duration_minutes',
        'order',
    ];

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
