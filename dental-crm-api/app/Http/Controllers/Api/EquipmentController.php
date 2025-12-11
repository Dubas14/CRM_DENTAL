<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->query('clinic_id');

        return Equipment::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $equipment = Equipment::create($data);

        return response()->json($equipment, 201);
    }

    public function show(Equipment $equipment)
    {
        return $equipment;
    }

    public function update(Request $request, Equipment $equipment)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $equipment->update($data);

        return $equipment;
    }

    public function destroy(Equipment $equipment)
    {
        $equipment->delete();

        return response()->noContent();
    }
}
