<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientToothStatus extends Model
{
    use HasFactory;
    protected $fillable = ['patient_id', 'tooth_number', 'status', 'note'];
}
