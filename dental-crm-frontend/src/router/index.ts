import { createRouter, createWebHistory } from 'vue-router'

import ClinicsList from '../views/ClinicsList.vue'
import DoctorListPage from '../features/doctors/DoctorListPage.vue'
import DoctorProfilePage from '../features/doctors/DoctorProfilePage.vue'
import DoctorSchedule from '../views/DoctorSchedule.vue'
import PatientsList from '../views/PatientsList.vue'
import PatientDetails from '../views/PatientDetails.vue'
import Login from '../views/LoginView.vue'
import DoctorWeeklySchedule from '../views/DoctorWeeklySchedule.vue'
import Dashboard from '../views/DashboardView.vue'
import EquipmentsList from '../views/EquipmentsList.vue'
import ProceduresList from '../views/ProceduresList.vue'
import AssistantsList from '../views/AssistantsList.vue'
import AssistantDetails from '../views/AssistantDetails.vue'
import Employees from '../views/Employees.vue'
import RoleManager from '../views/Settings/RoleManager.vue'
import ClinicSettings from '../views/ClinicSettings.vue'
import SpecializationsList from '../views/SpecializationsList.vue'
import InventoryListPage from '../views/InventoryListPage.vue'
import FinancePage from '../features/finance/FinancePage.vue'

import { useAuth } from '../composables/useAuth'
import { usePermissions } from '../composables/usePermissions'

const routes: import('vue-router').RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: Login },

  // ✅ Головна сторінка — Dashboard
  {
    path: '/',
    name: 'dashboard',
    component: Dashboard,
    meta: { requiresAuth: true }
  },

  // ✅ Основні модулі
  {
    path: '/schedule',
    name: 'schedule',
    component: DoctorSchedule,
    meta: { requiresAuth: true }
  },

  {
    path: '/calendar-board',
    name: 'calendar-board',
    component: () => import('../views/CalendarBoard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/equipments',
    name: 'equipments',
    component: EquipmentsList,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['equipment.view', 'equipment.manage', 'inventory.manage']
    }
  },
  {
    path: '/procedures',
    name: 'procedures',
    component: ProceduresList,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['procedure.view', 'procedure.manage', 'inventory.manage']
    }
  },
  {
    path: '/specializations',
    name: 'specializations',
    component: SpecializationsList,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin', 'doctor'],
      allowedPermissions: ['procedure.view', 'procedure.manage', 'specialization.view', 'specialization.manage']
    }
  },
  {
    path: '/inventory',
    name: 'inventory',
    component: InventoryListPage,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['inventory.view', 'inventory.manage']
    }
  },
  {
    path: '/finance',
    name: 'finance',
    component: FinancePage,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin', 'receptionist'],
      allowedPermissions: ['invoice.view', 'payment.view', 'finance.stats']
    }
  },
  {
    path: '/assistants',
    name: 'assistants',
    component: AssistantsList,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['user.view']
    }
  },
  {
    path: '/assistants/:id',
    name: 'assistant-details',
    component: AssistantDetails,
    props: true,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['user.view']
    }
  },
  {
    path: '/employees',
    name: 'employees',
    component: Employees,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['role.manage', 'user.view']
    }
  },
  {
    path: '/settings/roles',
    name: 'role-manager',
    component: RoleManager,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['role.manage']
    }
  },
  {
    path: '/clinic-settings',
    name: 'clinic-settings',
    component: ClinicSettings,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['clinic.view', 'clinic.update']
    }
  },

  {
    path: '/patients',
    name: 'patients',
    component: PatientsList,
    meta: { requiresAuth: true }
  },
  {
    path: '/patients/:id',
    name: 'patient-details',
    component: PatientDetails,
    props: true,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin', 'doctor'] }
  },

  // ✅ Адмінські розділи
  {
    path: '/clinics',
    name: 'clinics',
    component: ClinicsList,
    meta: {
      requiresAuth: true,
      superOnly: true,
      allowedPermissions: ['clinic.view', 'clinic.update']
    }
  },
  {
    path: '/doctors',
    name: 'doctors',
    component: DoctorListPage,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['user.view', 'clinic.view']
    }
  },
  {
    path: '/doctors/:id',
    name: 'doctor-details',
    component: DoctorProfilePage,
    props: true,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['user.view', 'clinic.view'],
      allowOwnDoctor: true
    }
  },
  {
    path: '/doctors/:id/schedule-settings',
    name: 'doctor-weekly-schedule',
    component: DoctorWeeklySchedule,
    props: true,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
      allowedPermissions: ['user.view', 'clinic.view'],
      allowOwnDoctor: true
    }
  },

  // ✅ fallback
  { path: '/:pathMatch(.*)*', redirect: '/' }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// guard
router.beforeEach(async (to, from, next) => {
  const publicPages: string[] = ['login']
  if (publicPages.includes(String(to.name ?? ''))) return next()

  const { user, fetchUser } = useAuth()
  const { permissions } = usePermissions()

  if (!user.value) {
    await fetchUser().catch(() => { })
  }

  if (!user.value) {
    return next({ name: 'login' })
  }

  const hasRequiredPermission = () => {
    if (!Array.isArray(to.meta.allowedPermissions)) return false
    const userPerms: string[] = permissions.value || []
    return to.meta.allowedPermissions.some((p: string) => userPerms.includes(p))
  }

  // 🔹 тільки супер-адмін (дозволяємо також, якщо є потрібні пермішени)
  if (to.meta.superOnly && user.value.global_role !== 'super_admin' && !hasRequiredPermission()) {
    return next({ name: 'schedule' })
  }

  // 🔹 ролі / пермішени
  if (Array.isArray(to.meta.allowedRoles) || Array.isArray(to.meta.allowedPermissions)) {
    const userRole = user.value.global_role
    const roleAllowed = Array.isArray(to.meta.allowedRoles)
      ? to.meta.allowedRoles.includes(userRole)
      : false
    const permAllowed = hasRequiredPermission()

    const isOwnDoctorRoute =
      to.meta.allowOwnDoctor &&
      userRole === 'doctor' &&
      user.value.doctor &&
      Number(to.params.id) === Number(user.value.doctor.id)

    if (!roleAllowed && !permAllowed && !isOwnDoctorRoute) {
      return next({ name: 'schedule' })
    }
  }

  return next()
})

export default router
