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
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $this->assertClinicAccess($request->user(), $data['clinic_id']);

        $item = InventoryItem::create($data);

        return response()->json($item, 201);
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
