<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'appointment_id',
        'invoice_number',
        'amount',
        'discount_amount',
        'discount_type',
        'paid_amount',
        'status',
        'is_prepayment',
        'description',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_prepayment' => 'boolean',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'total_amount',
        'paid_amount_formatted',
        'debt_amount',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID && $this->paid_amount >= $this->amount;
    }

    public function isPrepayment(): bool
    {
        return $this->is_prepayment === true;
    }

    public function getTotalAmountAttribute(): string
    {
        return $this->formatMoney($this->amount ?? 0);
    }

    public function getPaidAmountFormattedAttribute(): string
    {
        return $this->formatMoney($this->paid_amount ?? 0);
    }

    public function getDebtAmountAttribute(): string
    {
        $debt = ($this->amount ?? 0) - ($this->paid_amount ?? 0);
        if ($debt < 0) {
            $debt = 0;
        }
        return $this->formatMoney($debt);
    }

    public function syncStatusFromTotals(): void
    {
        $paid = (float) $this->paid_amount;
        $amount = (float) $this->amount;

        if ($amount <= 0) {
            $this->status = self::STATUS_UNPAID;
            return;
        }

        if ($paid <= 0) {
            $this->status = self::STATUS_UNPAID;
        } elseif ($paid < $amount) {
            $this->status = self::STATUS_PARTIALLY_PAID;
        } else {
            $this->status = self::STATUS_PAID;
        }
    }

    private function formatMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

