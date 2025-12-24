<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Procedure;
use App\Services\Access\DoctorAccessService;
use Illuminate\Http\Request;

class DoctorProcedureController extends Controller
{
    public function index(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageDoctor($user, $doctor)) {
            abort(403, 'У вас немає доступу до перегляду процедур цього лікаря');
        }

        $procedures = Procedure::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->orderBy('name')
            ->get();

        $assigned = $doctor->procedures()->get()->keyBy('id');

        $payload = $procedures->map(function (Procedure $procedure) use ($assigned) {
            $pivot = $assigned->get($procedure->id)?->pivot;

            return [
                'id' => $procedure->id,
                'name' => $procedure->name,
                'category' => $procedure->category,
                'duration_minutes' => $procedure->duration_minutes,
                'requires_room' => $procedure->requires_room,
                'requires_assistant' => $procedure->requires_assistant,
                'equipment_id' => $procedure->equipment_id,
                'is_assigned' => $pivot !== null,
                'custom_duration_minutes' => $pivot?->custom_duration_minutes,
            ];
        })->values();

        return response()->json($payload);
    }

    public function update(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if (!DoctorAccessService::canManageDoctor($user, $doctor)) {
            abort(403, 'У вас немає доступу до зміни процедур цього лікаря');
        }

        $data = $request->validate([
            'procedures' => ['required', 'array'],
            'procedures.*.procedure_id' => ['required', 'integer', 'exists:procedures,id'],
            'procedures.*.is_assigned' => ['required', 'boolean'],
            'procedures.*.custom_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $procedureIds = collect($data['procedures'])->pluck('procedure_id')->unique();
        $clinicProcedureCount = Procedure::where('clinic_id', $doctor->clinic_id)
            ->whereIn('id', $procedureIds)
            ->count();

        if ($clinicProcedureCount !== $procedureIds->count()) {
            abort(422, 'Деякі процедури не належать до цієї клініки');
        }

        $syncData = [];

        foreach ($data['procedures'] as $item) {
            if (!$item['is_assigned']) {
                continue;
            }

            $syncData[$item['procedure_id']] = [
                'custom_duration_minutes' => $item['custom_duration_minutes'] ?? null,
            ];
        }

        $doctor->procedures()->sync($syncData);

        return response()->json([
            'status' => 'updated',
        ]);
    }
}
