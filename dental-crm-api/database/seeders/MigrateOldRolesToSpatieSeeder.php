<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Support\RoleHierarchy;
use Spatie\Permission\Models\Role;

class MigrateOldRolesToSpatieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder converts old boolean flags (is_admin, etc.) to Spatie roles.
     * Run this BEFORE removing the old columns.
     */
    public function run(): void
    {
        // Ensure all system roles exist
        RoleHierarchy::ensureRolesExist();

        $users = User::all();

        foreach ($users as $user) {
            $rolesToAssign = [];

            // Check if user already has Spatie roles
            if ($user->roles()->count() > 0) {
                $this->command->info("User {$user->id} ({$user->email}) already has roles, skipping...");
                continue;
            }

            // Check old is_admin flag (if column still exists)
            if (Schema::hasColumn('users', 'is_admin')) {
                // This is a one-time migration, so we'll check the database directly
                $rawUser = DB::table('users')->where('id', $user->id)->first();
                
                if ($rawUser && isset($rawUser->is_admin) && $rawUser->is_admin) {
                    // If is_admin, check if they're super_admin or clinic_admin based on clinic_user pivot
                    $clinicAdminCount = $user->clinics()
                        ->wherePivot('clinic_role', 'clinic_admin')
                        ->count();
                    
                    if ($clinicAdminCount > 0) {
                        $rolesToAssign[] = 'clinic_admin';
                    } else {
                        // Default to super_admin if is_admin but no clinic_admin pivot
                        // This might need adjustment based on your business logic
                        $rolesToAssign[] = 'super_admin';
                    }
                }
            }

            // Check clinic_user pivot for role hints
            $clinicRoles = $user->clinics()->pluck('clinic_role')->unique()->filter();
            
            if ($clinicRoles->contains('clinic_admin')) {
                if (!in_array('clinic_admin', $rolesToAssign)) {
                    $rolesToAssign[] = 'clinic_admin';
                }
            }
            
            if ($clinicRoles->contains('doctor')) {
                $rolesToAssign[] = 'doctor';
            }
            
            if ($clinicRoles->contains('registrar')) {
                $rolesToAssign[] = 'registrar';
            }
            
            if ($clinicRoles->contains('assistant')) {
                $rolesToAssign[] = 'assistant';
            }

            // Check if user has a doctor profile
            if ($user->doctor && !in_array('doctor', $rolesToAssign)) {
                $rolesToAssign[] = 'doctor';
            }

            // Assign roles
            if (!empty($rolesToAssign)) {
                $user->syncRoles($rolesToAssign);
                $this->command->info("Assigned roles to user {$user->id} ({$user->email}): " . implode(', ', $rolesToAssign));
            } else {
                $this->command->warn("No roles found for user {$user->id} ({$user->email})");
            }
        }

        $this->command->info('Migration completed!');
    }
}

