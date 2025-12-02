<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientToothStatus extends Model
{
    protected $fillable = ['patient_id', 'tooth_number', 'status', 'note'];
}
