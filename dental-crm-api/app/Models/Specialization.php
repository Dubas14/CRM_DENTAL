<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_specialization');
    }
}


