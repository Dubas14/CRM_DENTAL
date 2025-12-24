<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicWorkingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'weekday',
        'is_working',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
    ];

    protected $casts = [
        'is_working' => 'boolean',
    ];
}
