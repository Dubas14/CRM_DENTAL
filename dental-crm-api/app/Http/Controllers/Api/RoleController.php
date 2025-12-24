<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

        RoleHierarchy::ensureRolesExist();

        $allowedRoles = RoleHierarchy::allowedRolesFor($authUser);
        $roles = array_values(array_intersect($allowedRoles, $data['roles'] ?? []));

        $user->syncRoles($roles);

        return response()->json([
            'roles' => $user->getRoleNames()->values(),
        ]);
    }
}
