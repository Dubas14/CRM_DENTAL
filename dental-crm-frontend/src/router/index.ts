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
import RolesManager from '../views/RolesManager.vue'
import ClinicSettings from '../views/ClinicSettings.vue'
import SpecializationsList from '../views/SpecializationsList.vue'

import { useAuth } from '../composables/useAuth'

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
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
  },
  {
    path: '/procedures',
    name: 'procedures',
    component: ProceduresList,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
  },
  {
    path: '/specializations',
    name: 'specializations',
    component: SpecializationsList,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
  },
  {
    path: '/assistants',
    name: 'assistants',
    component: AssistantsList,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
  },
  {
    path: '/assistants/:id',
    name: 'assistant-details',
    component: AssistantDetails,
    props: true,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
  },
  {
    path: '/roles',
    name: 'roles',
    component: RolesManager,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
  },
  {
    path: '/clinic-settings',
    name: 'clinic-settings',
    component: ClinicSettings,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
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
    meta: { requiresAuth: true, superOnly: true }
  },
  {
    path: '/doctors',
    name: 'doctors',
    component: DoctorListPage,
    meta: { requiresAuth: true, allowedRoles: ['super_admin', 'clinic_admin'] }
  },
  {
    path: '/doctors/:id',
    name: 'doctor-details',
    component: DoctorProfilePage,
    props: true,
    meta: {
      requiresAuth: true,
      allowedRoles: ['super_admin', 'clinic_admin'],
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

  if (!user.value) {
    await fetchUser().catch(() => { })
  }

  if (!user.value) {
    return next({ name: 'login' })
  }

  // 🔹 тільки супер-адмін
  if (to.meta.superOnly && user.value.global_role !== 'super_admin') {
    return next({ name: 'schedule' })
  }

  // 🔹 ролі
  if (Array.isArray(to.meta.allowedRoles)) {
    const userRole = user.value.global_role

    const isAllowed = to.meta.allowedRoles.includes(userRole)

    const isOwnDoctorRoute =
      to.meta.allowOwnDoctor &&
      userRole === 'doctor' &&
      user.value.doctor &&
      Number(to.params.id) === Number(user.value.doctor.id)

    if (!isAllowed && !isOwnDoctorRoute) {
      return next({ name: 'schedule' })
    }
  }

  return next()
})

export default router
