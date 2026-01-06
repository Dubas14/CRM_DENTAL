<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_USAGE = 'usage';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'clinic_id',
        'inventory_item_id',
        'type',
        'quantity',
        'cost_per_unit',
        'related_entity_type',
        'related_entity_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'cost_per_unit' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


