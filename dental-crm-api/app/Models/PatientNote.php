<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientNote extends Model
{
    use HasFactory;
    protected $fillable = ['patient_id', 'user_id', 'content'];

    // Автор нотатки
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
