<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RoleHierarchy
{
    public const ROLES = [
        'super_admin',
        'clinic_admin',
        'doctor',
        'registrar',
        'assistant',
    ];

    public static function allRoles(): array
    {
        return self::ROLES;
    }

    public static function ensureRolesExist(): void
    {
        // Використовуємо guard Sanctum, щоб збігався з токен-авторизацією
        $guard = config('auth.defaults.guard', 'sanctum');

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, $guard);
        }
    }

    public static function highestRole(array $roleNames): ?string
    {
        foreach (self::ROLES as $role) {
            if (in_array($role, $roleNames, true)) {
                return $role;
            }
        }

        return null;
    }

    public static function roleRank(?string $role): int
    {
        if (! $role) {
            return count(self::ROLES) + 1;
        }

        $index = array_search($role, self::ROLES, true);

        return $index === false ? count(self::ROLES) + 1 : $index;
    }

    public static function allowedRolesFor(User $user): array
    {
        $roles = $user->getRoleNames()->all();
        $highest = self::highestRole($roles);

        if ($highest === 'super_admin') {
            return self::ROLES;
        }

        $startIndex = self::roleRank($highest) + 1;

        return array_slice(self::ROLES, $startIndex);
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        if ($actor->hasRole('super_admin')) {
            return true;
        }

        if ($target->hasRole('super_admin')) {
            return false;
        }

        $actorHighest = self::highestRole($actor->getRoleNames()->all());
        $targetHighest = self::highestRole($target->getRoleNames()->all());

        return self::roleRank($actorHighest) < self::roleRank($targetHighest);
    }
}
