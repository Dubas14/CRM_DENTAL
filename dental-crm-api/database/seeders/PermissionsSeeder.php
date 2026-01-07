<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Calendar module
            'appointment.view',
            'appointment.create',
            'appointment.update',
            'appointment.delete',
            'appointment.cancel',
            'calendar.view',
            'calendar.manage',

            // Finance module
            'invoice.view',
            'invoice.create',
            'invoice.edit',
            'invoice.delete',
            'payment.view',
            'payment.create',
            'payment.refund',
            'finance.stats',
            'finance.export',

            // Inventory module
            'inventory.view',
            'inventory.manage',
            'inventory.transaction.create',
            'inventory.transaction.view',

            // Medical module
            'medical.view',
            'medical.edit',
            'medical.record.create',
            'medical.record.update',
            'patient.view',
            'patient.create',
            'patient.update',
            'patient.delete',

            // User management
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            'role.manage',

            // Clinic management
            'clinic.view',
            'clinic.create',
            'clinic.update',
            'clinic.delete',

            // Specializations
            'specialization.view',
            'specialization.manage',

            // Procedures catalog
            'procedure.view',
            'procedure.manage',
        ];

        // Використовуємо guard sanctum, щоб збігався з токен-авторизацією
        $guard = config('auth.defaults.guard', 'sanctum');

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }
    }
}

