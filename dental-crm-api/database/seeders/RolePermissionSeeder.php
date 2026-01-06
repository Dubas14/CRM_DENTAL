<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Support\RoleHierarchy;

class RolePermissionSeeder extends Seeder
{
    /**
     * Attach sensible default permissions to core roles.
     */
    public function run(): void
    {
        RoleHierarchy::ensureRolesExist();

        // Map of role => permissions
        $guard = config('auth.defaults.guard', 'sanctum');
        $map = [
            'super_admin' => Permission::all()->pluck('name')->all(),
            'clinic_admin' => [
                // Calendar
                'appointment.view',
                'appointment.create',
                'appointment.update',
                'appointment.delete',
                'appointment.cancel',
                'calendar.view',
                'calendar.manage',
                // Finance
                'invoice.view',
                'invoice.create',
                'invoice.update',
                'payment.collect',
                'payment.view',
                // Inventory
                'inventory.view',
                'inventory.manage',
                'inventory.transaction.create',
                'inventory.transaction.view',
                // Medical
                'medical.view',
                'medical.edit',
                'medical.record.create',
                'medical.record.update',
                'patient.view',
                'patient.create',
                'patient.update',
                // Users / Roles / Clinics
                'user.view',
                'user.create',
                'user.update',
                'role.manage',
                'clinic.view',
                'clinic.update',
            ],
            'doctor' => [
                'appointment.view',
                'appointment.create',
                'appointment.update',
                'calendar.view',
                'medical.view',
                'medical.edit',
                'medical.record.create',
                'medical.record.update',
                'patient.view',
                'patient.update',
            ],
            'assistant' => [
                'appointment.view',
                'appointment.create',
                'appointment.update',
                'calendar.view',
                'patient.view',
                'patient.create',
                'patient.update',
            ],
            'registrar' => [
                'appointment.view',
                'appointment.create',
                'appointment.update',
                'calendar.view',
                'patient.view',
                'patient.create',
                'patient.update',
            ],
        ];

        foreach ($map as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                continue;
            }

            $perms = Permission::whereIn('name', $permissions)
                ->where('guard_name', $guard)
                ->pluck('name')
                ->all();

            $role->syncPermissions($perms);
        }
    }
}

