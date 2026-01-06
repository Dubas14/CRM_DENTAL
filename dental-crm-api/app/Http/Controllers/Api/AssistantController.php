<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\User;
use App\Support\RoleHierarchy;
use App\Support\QuerySearch;
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

        // search filter (case-insensitive)
        if ($search = $request->string('search')->toString()) {
            QuerySearch::applyIlike(
                $query,
                $search,
                ['email', 'name', 'first_name', 'last_name'],
                ["concat_ws(' ', first_name, last_name) ILIKE ?"]
            );
        }

        $perPage = $request->integer('per_page', 50);
        $perPage = min(max($perPage, 1), 100);

        return response()->json($query->orderBy('name')->paginate($perPage));
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

    public function update(Request $request, User $assistant)
    {
        if (! $assistant->hasRole('assistant')) {
            abort(404);
        }

        $authUser = $request->user();
        $assistantClinicIds = $assistant->clinics()->pluck('clinics.id');

        if (! $authUser->isSuperAdmin()) {
            $hasAccess = $assistantClinicIds->contains(fn ($clinicId) => $authUser->hasClinicRole($clinicId, ['clinic_admin']));
            if (! $hasAccess) {
                abort(403, 'У вас немає права редагувати цього асистента');
            }
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$assistant->id],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $firstName = $data['first_name'] ?? $assistant->first_name;
            $lastName = $data['last_name'] ?? $assistant->last_name;
            $data['name'] = trim($firstName.' '.$lastName);
        }

        $assistant->update($data);

        return $assistant->load(['roles', 'clinics:id,name']);
    }

    public function show(Request $request, User $assistant)
    {
        if (! $assistant->hasRole('assistant')) {
            abort(404);
        }

        $authUser = $request->user();
        $assistantClinicIds = $assistant->clinics()->pluck('clinics.id');

        if (! $authUser->isSuperAdmin()) {
            $hasAccess = $assistantClinicIds->contains(fn ($clinicId) => $authUser->hasClinicRole($clinicId, ['clinic_admin']));
            if (! $hasAccess) {
                abort(403, 'У вас немає права переглядати цього асистента');
            }
        }

        return $assistant->load(['roles', 'clinics:id,name']);
    }

    public function destroy(Request $request, User $assistant)
    {
        if (! $assistant->hasRole('assistant')) {
            abort(404);
        }

        $authUser = $request->user();
        $assistantClinicIds = $assistant->clinics()->pluck('clinics.id');

        if (! $authUser->isSuperAdmin()) {
            $hasAccess = $assistantClinicIds->contains(fn ($clinicId) => $authUser->hasClinicRole($clinicId, ['clinic_admin']));
            if (! $hasAccess) {
                abort(403, 'У вас немає права видаляти цього асистента');
            }
        }

        $assistant->delete();

        return response()->noContent();
    }
}
