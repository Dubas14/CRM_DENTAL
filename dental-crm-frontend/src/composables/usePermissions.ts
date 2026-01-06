import { computed } from 'vue'
import { useAuth } from './useAuth'

export function usePermissions() {
  const { user } = useAuth()

  const role = computed(() => user.value?.global_role || 'guest')
  const permissions = computed<string[]>(() => user.value?.permissions || [])

  const hasPermission = (perm: string) => {
    return permissions.value.includes(perm)
  }

  const isSuperAdmin = computed(() => role.value === 'super_admin')
  const isClinicAdmin = computed(() => role.value === 'clinic_admin')
  const isDoctor = computed(() => role.value === 'doctor')
  const isRegistrar = computed(() => role.value === 'registrar')

  const canSeeClinics = computed(
    () => isSuperAdmin.value || isClinicAdmin.value || hasPermission('clinic.view')
  )

  const canSeeDoctors = computed(
    () => isSuperAdmin.value || isClinicAdmin.value || hasPermission('user.view')
  )

  const canSeeSchedule = computed(
    () =>
      isSuperAdmin.value ||
      isClinicAdmin.value ||
      isDoctor.value ||
      isRegistrar.value ||
      hasPermission('appointment.view') ||
      hasPermission('calendar.view')
  )

  const canSeePatients = computed(
    () =>
      isSuperAdmin.value ||
      isClinicAdmin.value ||
      isDoctor.value ||
      isRegistrar.value ||
      hasPermission('patient.view')
  )

  const canManageRoles = computed(
    () => isSuperAdmin.value || isClinicAdmin.value || hasPermission('role.manage')
  )

  const canManageCatalog = computed(
    () =>
      isSuperAdmin.value ||
      isClinicAdmin.value ||
      hasPermission('inventory.view') ||
      hasPermission('inventory.manage') ||
      hasPermission('procedure.view')
  )

  return {
    role,
    permissions,
    hasPermission,
    isSuperAdmin,
    isClinicAdmin,
    isDoctor,
    isRegistrar,
    canSeeClinics,
    canSeeDoctors,
    canSeeSchedule,
    canSeePatients,
    canManageRoles,
    canManageCatalog
  }
}
