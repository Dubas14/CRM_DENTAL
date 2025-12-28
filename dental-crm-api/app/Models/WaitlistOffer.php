<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitlistOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'waitlist_entry_id',
        'token',
        'status',
        'expires_at',
        'claimed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function waitlistEntry()
    {
        return $this->belongsTo(WaitlistEntry::class);
    }
}
