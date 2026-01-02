<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'appointment_id',
        'procedure_id',
        'invoice_number',
        'amount',
        'paid_amount',
        'status',
        'is_prepayment',
        'description',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_prepayment' => 'boolean',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID && $this->paid_amount >= $this->amount;
    }

    public function isPrepayment(): bool
    {
        return $this->is_prepayment === true;
    }
}

