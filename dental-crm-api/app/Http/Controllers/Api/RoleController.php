<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Clinic;
use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        RoleHierarchy::ensureRolesExist();

        return response()->json([
            'roles' => RoleHierarchy::allowedRolesFor($authUser),
        ]);
    }

    public function users(Request $request)
    {
        $authUser = $request->user();

        $query = User::query()
            ->with('roles')
            ->select(['id', 'name', 'first_name', 'last_name', 'email']);

        $role = $request->query('role');
        if ($role) {
            $query->role($role);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_').'%';
            $query->where(function ($q) use ($like) {
                $q->where('email', 'ilike', $like)
                    ->orWhere('name', 'ilike', $like)
                    ->orWhere('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhereRaw("concat_ws(' ', first_name, last_name) ILIKE ?", [$like]);
            });
        }

        if (! $authUser->hasRole('super_admin')) {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function updateUserRoles(Request $request, User $user)
    {
        $authUser = $request->user();

        if (! RoleHierarchy::canManageUser($authUser, $user)) {
            abort(403, 'Недостатньо прав для зміни ролей');
        }

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'], // для автоматичного прив'язування лікаря/асистента
        ]);

        RoleHierarchy::ensureRolesExist();

        $allowedRoles = RoleHierarchy::allowedRolesFor($authUser);
        $roles = array_values(array_intersect($allowedRoles, $data['roles'] ?? []));

        $user->syncRoles($roles);

        // Auto-provision doctor/assistant profile & clinic binding
        $clinicId = $data['clinic_id'] ?? null;
        $this->ensureDoctorProfile($user, $clinicId);
        $this->ensureAssistantBinding($user, $clinicId);

        return response()->json([
            'roles' => $user->getRoleNames()->values(),
        ]);
    }

    private function ensureDoctorProfile(User $user, ?int $clinicId = null): void
    {
        if (! $user->hasRole('doctor')) {
            return;
        }

        $primaryClinicId = $clinicId;
        if (! $primaryClinicId) {
            $primaryClinicId = $user->clinics()->pluck('clinics.id')->first();
        }

        // Без клініки не створюємо профіль, щоб не порушити NOT NULL.
        if (! $primaryClinicId) {
            abort(422, 'Необхідно вказати clinic_id для ролі doctor');
        }

        $doctor = $user->doctor;

        if (! $doctor) {
            $fullName = trim(($user->name ?? '').' '.($user->last_name ?? ''));
            if ($fullName === '') {
                $fullName = $user->email ?? 'Лікар';
            }

            $doctor = Doctor::create([
                'user_id'   => $user->id,
                'clinic_id' => $primaryClinicId,
                'full_name' => $fullName,
                'email'     => $user->email,
                'is_active' => true,
            ]);
        } elseif ($primaryClinicId && $doctor->clinic_id === null) {
            $doctor->clinic_id = $primaryClinicId;
            $doctor->save();
        }

        // Прив'язуємо до клініки як лікаря
        if ($primaryClinicId) {
            $user->clinics()->syncWithoutDetaching([
                $primaryClinicId => ['clinic_role' => 'doctor'],
            ]);
            $doctor->clinics()->syncWithoutDetaching([$primaryClinicId]);
        }
    }

    private function ensureAssistantBinding(User $user, ?int $clinicId = null): void
    {
        if (! $user->hasRole('assistant')) {
            return;
        }

        $targetClinicId = $clinicId;
        if (! $targetClinicId) {
            $targetClinicId = $user->clinics()->pluck('clinics.id')->first();
        }

        if ($targetClinicId) {
            $user->clinics()->syncWithoutDetaching([
                $targetClinicId => ['clinic_role' => 'assistant'],
            ]);
        }
    }
}
