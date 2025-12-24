<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AssistantController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        $query = User::query()
            ->with(['roles', 'clinics:id,name'])
            ->role('assistant');

        $clinicId = $request->query('clinic_id');
        if ($clinicId) {
            $query->whereHas('clinics', fn ($q) => $q->where('clinics.id', $clinicId));
        }

        if (! $authUser->hasRole('super_admin')) {
            $clinicIds = $authUser->clinics()
                ->wherePivot('clinic_role', 'clinic_admin')
                ->pluck('clinics.id');

            $query->whereHas('clinics', fn ($q) => $q->whereIn('clinics.id', $clinicIds));
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $authUser = $request->user();

        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (! $authUser->isSuperAdmin() && ! $authUser->hasClinicRole($data['clinic_id'], ['clinic_admin'])) {
            abort(403, 'У вас немає права створювати асистентів для цієї клініки');
        }

        RoleHierarchy::ensureRolesExist();

        $assistant = DB::transaction(function () use ($data) {
            $fullName = trim($data['first_name'].' '.$data['last_name']);

            $user = User::create([
                'name' => $fullName,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole('assistant');

            $clinic = Clinic::findOrFail($data['clinic_id']);
            $clinic->users()->syncWithoutDetaching([
                $user->id => ['clinic_role' => 'assistant'],
            ]);

            return $user->load(['roles', 'clinics:id,name']);
        });

        return response()->json($assistant, 201);
    }
}
