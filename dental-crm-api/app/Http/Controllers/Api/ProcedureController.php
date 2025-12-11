<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use Illuminate\Http\Request;

class ProcedureController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->query('clinic_id');

        return Procedure::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id'          => ['required', 'exists:clinics,id'],
            'name'               => ['required', 'string', 'max:255'],
            'category'           => ['nullable', 'string', 'max:255'],
            'duration_minutes'   => ['required', 'integer', 'min:5', 'max:480'],
            'requires_room'      => ['boolean'],
            'requires_assistant' => ['boolean'],
            'default_room_id'    => ['nullable', 'exists:rooms,id'],
            'metadata'           => ['nullable', 'array'],
        ]);

        $procedure = Procedure::create($data);

        return response()->json($procedure, 201);
    }

    public function show(Procedure $procedure)
    {
        return $procedure->load('defaultRoom');
    }

    public function update(Request $request, Procedure $procedure)
    {
        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'category'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'duration_minutes'   => ['sometimes', 'integer', 'min:5', 'max:480'],
            'requires_room'      => ['sometimes', 'boolean'],
            'requires_assistant' => ['sometimes', 'boolean'],
            'default_room_id'    => ['sometimes', 'nullable', 'exists:rooms,id'],
            'metadata'           => ['sometimes', 'nullable', 'array'],
        ]);

        $procedure->update($data);

        return $procedure->load('defaultRoom');
    }

    public function destroy(Procedure $procedure)
    {
        $procedure->delete();

        return response()->noContent();
    }
}
