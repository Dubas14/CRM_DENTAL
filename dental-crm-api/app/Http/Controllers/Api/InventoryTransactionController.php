<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryTransactionController extends Controller
{
    public function __construct(private InventoryService $inventoryService)
    {
    }

    public function index(Request $request)
    {
        $query = InventoryTransaction::query()->with(['item', 'clinic', 'creator']);

        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->integer('clinic_id'));
        }

        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->integer('inventory_item_id'));
        }

        $perPage = min(max($request->integer('per_page', 50), 1), 200);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'type' => ['required', Rule::in([
                InventoryTransaction::TYPE_PURCHASE,
                InventoryTransaction::TYPE_USAGE,
                InventoryTransaction::TYPE_ADJUSTMENT,
            ])],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'related_entity_type' => ['nullable', 'string', 'max:50'],
            'related_entity_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string'],
        ]);

        $this->assertClinicAccess($request->user(), $data['clinic_id']);

        $tx = $this->inventoryService->createTransaction($data, $request->user());

        return response()->json($tx, 201);
    }

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }
}


