<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\QuerySearch;

class ProcedureController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->query('clinic_id');
        $perPage = $request->integer('per_page', 50);
        $perPage = min(max($perPage, 1), 100);

        $query = Procedure::query()
            ->with(['steps', 'rooms'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));

        // search filter (case-insensitive)
        if ($search = $request->string('search')->toString()) {
            $searchTerm = trim($search);
            if (!empty($searchTerm)) {
                $like = '%' . addcslashes($searchTerm, '%_') . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'ilike', $like)
                        ->orWhere('category', 'ilike', $like)
                        ->orWhereHas('steps', function ($q) use ($like) {
                            $q->where('name', 'ilike', $like);
                        });
                });
            }
        }

        return $query->orderBy('name')->paginate($perPage);
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
            'equipment_id'       => ['nullable', 'exists:equipments,id'],
            'metadata'           => ['nullable', 'array'],
            'steps'              => ['nullable', 'array'],
            'steps.*.name'       => ['required_with:steps', 'string', 'max:255'],
            'steps.*.duration_minutes' => ['required_with:steps', 'integer', 'min:5', 'max:480'],
            'steps.*.order'      => ['required_with:steps', 'integer', 'min:1'],
            'room_ids'           => ['nullable', 'array'],
            'room_ids.*'         => ['integer', 'exists:rooms,id'],
        ]);

        $procedure = DB::transaction(function () use ($data) {
            $procedure = Procedure::create($data);

            if (!empty($data['steps'])) {
                $steps = collect($data['steps'])
                    ->sortBy('order')
                    ->values();

                $procedure->steps()->createMany($steps->toArray());
            }

            if (array_key_exists('room_ids', $data)) {
                $procedure->rooms()->sync($data['room_ids'] ?? []);
            }

            return $procedure;
        });

        return response()->json($procedure->load(['steps', 'rooms']), 201);
    }

    public function show(Procedure $procedure)
    {
        return $procedure->load(['defaultRoom', 'steps', 'rooms']);
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
            'equipment_id'       => ['sometimes', 'nullable', 'exists:equipments,id'],
            'metadata'           => ['sometimes', 'nullable', 'array'],
            'steps'              => ['sometimes', 'array'],
            'steps.*.name'       => ['required_with:steps', 'string', 'max:255'],
            'steps.*.duration_minutes' => ['required_with:steps', 'integer', 'min:5', 'max:480'],
            'steps.*.order'      => ['required_with:steps', 'integer', 'min:1'],
            'room_ids'           => ['sometimes', 'nullable', 'array'],
            'room_ids.*'         => ['integer', 'exists:rooms,id'],
        ]);

        DB::transaction(function () use ($procedure, $data) {
            $procedure->update($data);

            if (array_key_exists('steps', $data)) {
                $procedure->steps()->delete();

                if (!empty($data['steps'])) {
                    $steps = collect($data['steps'])
                        ->sortBy('order')
                        ->values();

                    $procedure->steps()->createMany($steps->toArray());
                }
            }

            if (array_key_exists('room_ids', $data)) {
                $procedure->rooms()->sync($data['room_ids'] ?? []);
            }
        });

        return $procedure->load(['defaultRoom', 'steps', 'rooms']);
    }

    public function destroy(Procedure $procedure)
    {
        $procedure->delete();

        return response()->noContent();
    }
}
