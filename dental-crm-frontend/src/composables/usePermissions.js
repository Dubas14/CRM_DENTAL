import { computed } from 'vue';
import { useAuth } from './useAuth';

export function usePermissions() {
    const { user } = useAuth();

    const role = computed(() => user.value?.global_role || 'guest');

    const isSuperAdmin = computed(() => role.value === 'super_admin');
    const isClinicAdmin = computed(() => role.value === 'clinic_admin');
    const isDoctor = computed(() => role.value === 'doctor');
    const isRegistrar = computed(() => role.value === 'registrar');

    const canSeeClinics = computed(() =>
        isSuperAdmin.value || isClinicAdmin.value
    );

    const canSeeDoctors = computed(() =>
        isSuperAdmin.value || isClinicAdmin.value
    );

    const canSeeSchedule = computed(() =>
        isSuperAdmin.value || isClinicAdmin.value || isDoctor.value || isRegistrar.value
    );

    const canSeePatients = computed(() =>
        isSuperAdmin.value || isClinicAdmin.value || isDoctor.value || isRegistrar.value
    );

    return {
        role,
        isSuperAdmin,
        isClinicAdmin,
        isDoctor,
        isRegistrar,
        canSeeClinics,
        canSeeDoctors,
        canSeeSchedule,
        canSeePatients,
    };
}
