<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

    /**
     * Get all roles with their permissions (for RoleManager constructor)
     */
    public function listRoles(Request $request)
    {
        $authUser = $request->user();

        // super_admin / clinic_admin OR permission role.manage
        if (! $authUser->hasAnyRole(['super_admin', 'clinic_admin']) && ! $authUser->can('role.manage')) {
            abort(403, 'Недостатньо прав');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        });

        $permissions = Permission::all()->groupBy(function ($permission) {
            // Group by module prefix (e.g., 'appointment.view' -> 'appointment')
            $parts = explode('.', $permission->name);

            return $parts[0] ?? 'other';
        })->map(function ($group, $module) {
            return [
                'module' => $module,
                'permissions' => $group->map(function ($perm) {
                    return [
                        'id' => $perm->id,
                        'name' => $perm->name,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create or update a role with permissions
     */
    public function storeRole(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->hasAnyRole(['super_admin', 'clinic_admin']) && ! $authUser->can('role.manage')) {
            abort(403, 'Недостатньо прав');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $data['name']]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->toArray(),
        ]);
    }

    /**
     * Update role permissions
     */
    public function updateRole(Request $request, Role $role)
    {
        $authUser = $request->user();

        if (! $authUser->hasAnyRole(['super_admin', 'clinic_admin']) && ! $authUser->can('role.manage')) {
            abort(403, 'Недостатньо прав');
        }

        // Якщо це системна роль, дозволяємо редагування лише супер-адміну
        if (in_array($role->name, RoleHierarchy::ROLES) && ! $authUser->hasRole('super_admin')) {
            abort(422, 'Неможливо редагувати системну роль');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (isset($data['name']) && $data['name'] !== $role->name) {
            $role->name = $data['name'];
            $role->save();
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->toArray(),
        ]);
    }

    public function users(Request $request)
    {
        $authUser = $request->user();

        $query = User::query()
            ->with('roles')
            ->select(['id', 'name', 'first_name', 'last_name', 'email']);

        // Filter by clinic_id for clinic admins
        if (! $authUser->hasRole('super_admin')) {
            $clinicIds = $authUser->clinics()->pluck('clinics.id');
            if ($clinicIds->isNotEmpty()) {
                $query->whereHas('clinics', fn ($q) => $q->whereIn('clinics.id', $clinicIds));
            } else {
                // If user has no clinics, return empty
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ]);
            }
        }

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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

    /**
     * Assign a single role to a user (new endpoint for dropdown-based assignment)
     */
    public function assignRole(Request $request, User $user)
    {
        $authUser = $request->user();

        if (! RoleHierarchy::canManageUser($authUser, $user)) {
            abort(403, 'Недостатньо прав для зміни ролей');
        }

        $data = $request->validate([
            'role_name' => ['required', 'string'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        RoleHierarchy::ensureRolesExist();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Check if role exists (either system role or custom role)
        $role = Role::where('name', $data['role_name'])->first();
        if (! $role) {
            abort(422, 'Роль не знайдена');
        }

        // For system roles, check hierarchy
        if (in_array($data['role_name'], RoleHierarchy::ROLES)) {
            $allowedRoles = RoleHierarchy::allowedRolesFor($authUser);
            if (! in_array($data['role_name'], $allowedRoles)) {
                abort(403, 'Недостатньо прав для призначення цієї ролі');
            }
        }

        // Assign single role (replace all existing roles)
        $user->syncRoles([$data['role_name']]);

        // Auto-provision doctor/assistant profile & clinic binding
        $clinicId = $data['clinic_id'] ?? null;
        $this->ensureDoctorProfile($user, $clinicId);
        $this->ensureAssistantBinding($user, $clinicId);

        return response()->json([
            'role' => $data['role_name'],
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
                'user_id' => $user->id,
                'clinic_id' => $primaryClinicId,
                'full_name' => $fullName,
                'email' => $user->email,
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
