<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->query('clinic_id');
        $perPage = $request->integer('per_page', 50);
        $perPage = min(max($perPage, 1), 100);

        return Room::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'name'      => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'notes'     => ['nullable', 'string'],
        ]);

        $room = Room::create($data);

        return response()->json($room, 201);
    }

    public function show(Room $room)
    {
        return $room;
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'equipment' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'     => ['sometimes', 'nullable', 'string'],
        ]);

        $room->update($data);

        return $room;
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return response()->noContent();
    }
}
