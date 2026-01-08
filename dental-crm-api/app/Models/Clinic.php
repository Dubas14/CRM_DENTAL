<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'address',
        'city',
        'country',
        'postal_code',
        'lat',
        'lng',
        'phone',
        'email',
        'website',
        'is_active',
        'logo_url',
        'phone_main',
        'email_public',
        'address_street',
        'address_building',
        'slogan',
        'currency_code',
        'requisites',
    ];

    protected $casts = [
        'requisites' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'clinic_user')
            ->withPivot('clinic_role')
            ->withTimestamps();
    }

    public function workingHours()
    {
        return $this->hasMany(ClinicWorkingHour::class);
    }
}
