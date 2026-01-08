<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query()->with('clinic');

        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->integer('clinic_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%');
            });
        }

        return $query->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', 'string', 'max:20'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $this->assertClinicAccess($request->user(), $data['clinic_id']);

        // Handle initial_stock: create item and transaction in one DB transaction
        $item = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            // Set current_stock from initial_stock if provided, otherwise use current_stock or 0
            $currentStock = isset($data['initial_stock']) && $data['initial_stock'] > 0
                ? $data['initial_stock']
                : ($data['current_stock'] ?? 0);

            // Generate code automatically if not provided
            $code = $data['code'] ?? null;
            if (empty($code)) {
                // Get the last item for this clinic to generate next code
                $lastItem = \App\Models\InventoryItem::where('clinic_id', $data['clinic_id'])
                    ->whereNotNull('code')
                    ->where('code', 'like', 'MAT%')
                    ->orderByDesc('id')
                    ->first();

                if ($lastItem && preg_match('/MAT(\d+)/', $lastItem->code, $matches)) {
                    $nextNumber = (int) $matches[1] + 1;
                    $code = 'MAT' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
                } else {
                    $code = 'MAT0001';
                }

                // Ensure uniqueness
                $counter = 1;
                while (\App\Models\InventoryItem::where('clinic_id', $data['clinic_id'])
                    ->where('code', $code)
                    ->exists()) {
                    $code = 'MAT' . str_pad((string) ((int) substr($code, 3) + $counter), 4, '0', STR_PAD_LEFT);
                    $counter++;
                }
            }

            $itemData = [
                'clinic_id' => $data['clinic_id'],
                'name' => $data['name'],
                'code' => $code,
                'unit' => $data['unit'],
                'current_stock' => $currentStock,
                'min_stock_level' => $data['min_stock_level'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ];

            $item = \App\Models\InventoryItem::create($itemData);

            // If initial_stock > 0, create an adjustment transaction
            if (isset($data['initial_stock']) && $data['initial_stock'] > 0) {
                \App\Models\InventoryTransaction::create([
                    'clinic_id' => $data['clinic_id'],
                    'inventory_item_id' => $item->id,
                    'type' => \App\Models\InventoryTransaction::TYPE_ADJUSTMENT,
                    'quantity' => $data['initial_stock'],
                    'cost_per_unit' => null,
                    'related_entity_type' => null,
                    'related_entity_id' => null,
                    'note' => 'Початковий залишок',
                    'created_by' => $request->user()->id,
                ]);
            }

            return $item;
        });

        return response()->json($item->load('transactions'), 201);
    }

    public function update(Request $request, InventoryItem $inventory_item)
    {
        $this->assertClinicAccess($request->user(), $inventory_item->clinic_id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'min_stock_level' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $inventory_item->update($data);

        return response()->json($inventory_item);
    }

    public function destroy(InventoryItem $inventory_item)
    {
        $this->assertClinicAccess(request()->user(), $inventory_item->clinic_id);
        $inventory_item->delete();

        return response()->noContent();
    }

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }
}
