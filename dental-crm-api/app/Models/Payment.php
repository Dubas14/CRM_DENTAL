<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const METHODS = ['cash', 'card', 'bank_transfer', 'insurance'];

    protected $fillable = [
        'clinic_id',
        'invoice_id',
        'amount',
        'method',
        'transaction_id',
        'created_by',
        'is_refund',
        'refund_reason',
        'original_payment_id',
        'refunded_by',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_refund' => 'boolean',
        'refunded_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'original_payment_id');
    }
}
