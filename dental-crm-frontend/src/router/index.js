import { createRouter, createWebHistory } from 'vue-router';
import ClinicsList from '../views/ClinicsList.vue';
import DoctorsList from '../views/DoctorsList.vue';
import DoctorDetails from '../views/DoctorDetails.vue';
import DoctorSchedule from '../views/DoctorSchedule.vue';
import PatientsList from '../views/PatientsList.vue';
import PatientDetails from '../views/PatientDetails.vue';
import Login from '../views/Login.vue';
import { useAuth } from '../composables/useAuth';
import DoctorWeeklySchedule from '../views/DoctorWeeklySchedule.vue';


const routes = [
    { path: '/login', name: 'login', component: Login },

    { path: '/', redirect: '/clinics' },
    {
        path: '/clinics',
        name: 'clinics',
        component: ClinicsList,
        meta: { superOnly: true }, // 🔹 тільки супер-адмін
    },
    {
        path: '/doctors',
        name: 'doctors',
        component: DoctorsList,
        meta: { allowedRoles: ['super_admin', 'clinic_admin'] },
    },

    {
        path: '/doctors/:id',
        name: 'doctor-details',
        component: DoctorDetails,
        props: true,
        meta: { allowedRoles: ['super_admin', 'clinic_admin'], allowOwnDoctor: true },
    },
    { path: '/schedule', name: 'schedule', component: DoctorSchedule },
    { path: '/patients', name: 'patients', component: PatientsList },
    {
        path: '/patients/:id',
        name: 'patient-details',
        component: PatientDetails,
        props: true,
        meta: { allowedRoles: ['super_admin', 'clinic_admin', 'doctor'] },
    },

    {
        path: '/doctors/:id/schedule-settings',
        name: 'doctor-weekly-schedule',
        component: DoctorWeeklySchedule,
        props: true,
        meta: { allowedRoles: ['super_admin', 'clinic_admin'], allowOwnDoctor: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// простий guard
router.beforeEach(async (to, from, next) => {
    const publicPages = ['login'];
    if (publicPages.includes(to.name)) return next();

    const { user, fetchUser } = useAuth();

    if (!user.value) {
        await fetchUser().catch(() => {});
    }

    if (!user.value) {
        return next({ name: 'login' });
    }
    // 🔹 якщо маршрут лише для супер-адміна
    if (to.meta.superOnly && user.value.global_role !== 'super_admin') {
        return next({ name: 'schedule' }); // лікарів кидаємо на розклад
    }
    if (to.meta.allowedRoles) {
        const userRole = user.value.global_role;
        const isAllowed = to.meta.allowedRoles.includes(userRole);
        const isOwnDoctorRoute =
            to.meta.allowOwnDoctor &&
            userRole === 'doctor' &&
            user.value.doctor &&
            Number(to.params.id) === Number(user.value.doctor.id);

        if (!isAllowed && !isOwnDoctorRoute) {
            return next({ name: 'schedule' });
        }
    }

    return next();
});

export default router;
