<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function createTransaction(array $data, User $user): InventoryTransaction
    {
        return DB::transaction(function () use ($data, $user) {
            /** @var InventoryItem $item */
            $item = InventoryItem::where('clinic_id', $data['clinic_id'])
                ->lockForUpdate()
                ->findOrFail($data['inventory_item_id']);

            $type = $data['type'];
            $quantity = $this->toFloat($data['quantity']);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Кількість має бути більшою за 0']);
            }

            $delta = match ($type) {
                InventoryTransaction::TYPE_PURCHASE => $quantity,
                InventoryTransaction::TYPE_USAGE => -$quantity,
                InventoryTransaction::TYPE_ADJUSTMENT => $quantity,
                default => throw ValidationException::withMessages(['type' => 'Невідомий тип транзакції']),
            };

            $newStock = $this->toFloat($item->current_stock) + $delta;
            if ($newStock < -1e-6) {
                throw ValidationException::withMessages(['quantity' => 'Недостатньо залишку для списання']);
            }

            $transaction = InventoryTransaction::create([
                'clinic_id' => $item->clinic_id,
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => $this->formatQty($quantity),
                'cost_per_unit' => isset($data['cost_per_unit']) ? $this->formatMoney($data['cost_per_unit']) : null,
                'related_entity_type' => $data['related_entity_type'] ?? null,
                'related_entity_id' => $data['related_entity_id'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
            ]);

            $item->current_stock = $this->formatQty(max(0, $newStock));
            $item->save();

            return $transaction->fresh('item');
        });
    }

    private function formatMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function formatQty($value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    private function toFloat($value): float
    {
        return (float) number_format((float) $value, 3, '.', '');
    }
}
