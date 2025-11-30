<?php

namespace App\Services\Access;

use App\Models\Doctor;
use App\Models\User;

class DoctorAccessService
{
    public static function canManageDoctor(User $user, Doctor $doctor): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasClinicRole($doctor->clinic_id, ['clinic_admin'])) {
            return true;
        }

        if ($doctor->user_id && $doctor->user_id === $user->id) {
            return true;
        }

        return false;
    }

    public static function canManageAppointments(User $user, Doctor $doctor): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasClinicRole($doctor->clinic_id, ['clinic_admin', 'registrar'])) {
            return true;
        }

        if ($doctor->user_id && $doctor->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
