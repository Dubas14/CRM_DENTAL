<script setup>
import { ref, onMounted } from 'vue';
import { useAuth } from '../composables/useAuth';
import apiClient from '../services/apiClient';
import { Users, Calendar, Clock, Activity } from 'lucide-vue-next';
import ActivityChart from '../components/ActivityChart.vue';

const { user } = useAuth();
const stats = ref({
  patientsCount: 0,
  appointmentsToday: 0,
  nextAppointment: null
});

const loading = ref(true);
const weeklyActivity = ref([
  { day: 'Пн', value: 12 },
  { day: 'Вт', value: 18 },
  { day: 'Ср', value: 10 },
  { day: 'Чт', value: 22 },
  { day: 'Пт', value: 16 },
  { day: 'Сб', value: 8 },
  { day: 'Нд', value: 5 }
]);


// Імітація завантаження статистики (поки бекенд не має спеціального ендпоінта)
// Ми можемо зробити окремий контролер для цього пізніше.
const loadStats = async () => {
  loading.value = true;
  try {
    // Завантажуємо пацієнтів (просто кількість для прикладу)
    const { data: patients } = await apiClient.get('/patients');
    stats.value.patientsCount = patients.total || 0;

    // Завантажуємо записи на сьогодні
    const today = new Date().toISOString().slice(0, 10);
    // Тут потрібен роут для записів поточного лікаря.
    // Використовуємо існуючий, якщо є doctor_id, або просто заглушку для демо.
    if (user.value.doctor) {
      const { data: apps } = await apiClient.get(`/doctors/${user.value.doctor.id}/appointments`, { params: { date: today } });
      stats.value.appointmentsToday = apps.length;
      stats.value.nextAppointment = apps.find(a => a.status !== 'done') || null;
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

onMounted(loadStats);
</script>

<template>
  <div class="space-y-6 animate-fade-in">
    <!-- Привітання -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 p-8 text-white shadow-lg">
      <div class="relative z-10">
        <h1 class="text-3xl font-bold mb-2">Вітаємо, {{ user?.first_name }}! 👋</h1>
        <p class="text-emerald-100 text-lg">Гарного робочого дня. Сьогодні у вас {{ stats.appointmentsToday }} пацієнтів.</p>
      </div>
      <!-- Декоративні кола -->
      <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
      <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
    </div>

    <!-- Картки статистики -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Пацієнти -->
      <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-md hover:shadow-xl hover:border-emerald-500/30 transition-all duration-300 group">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-slate-400 text-sm font-medium uppercase">Всього пацієнтів</p>
            <h3 class="text-3xl font-bold text-white mt-2">{{ stats.patientsCount }}</h3>
          </div>
          <div class="p-3 bg-slate-800 rounded-lg text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
            <Users size="24" />
          </div>
        </div>
      </div>

      <!-- Записи сьогодні -->
      <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-md hover:shadow-xl hover:border-blue-500/30 transition-all duration-300 group">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-slate-400 text-sm font-medium uppercase">Записи сьогодні</p>
            <h3 class="text-3xl font-bold text-white mt-2">{{ stats.appointmentsToday }}</h3>
          </div>
          <div class="p-3 bg-slate-800 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-colors">
            <Calendar size="24" />
          </div>
        </div>
      </div>

      <!-- Активність -->
      <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-md hover:shadow-xl hover:border-purple-500/30 transition-all duration-300 group">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-slate-400 text-sm font-medium uppercase">Найближчий візит</p>
            <h3 class="text-xl font-bold text-white mt-2 truncate">
              {{ stats.nextAppointment ? stats.nextAppointment.time.slice(0,5) : '—' }}
            </h3>
            <p class="text-xs text-slate-500 mt-1" v-if="stats.nextAppointment">
              {{ stats.nextAppointment.patient_name || 'Без імені' }}
            </p>
          </div>
          <div class="p-3 bg-slate-800 rounded-lg text-purple-400 group-hover:bg-purple-500 group-hover:text-white transition-colors">
            <Clock size="24" />
          </div>
        </div>
      </div>
    </div>

    <!-- Секція швидких дій -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">␊
        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">␊
          <Activity size="20" class="text-emerald-400"/>␊
          Швидкі дії␊
        </h3>␊
        <div class="grid grid-cols-2 gap-4">
          <router-link :to="{name: 'schedule'}" class="flex flex-col items-center justify-center p-4 bg-slate-950 border border-slate-800 rounded-lg hover:bg-slate-800 transition-colors cursor-pointer group">
            <Calendar class="text-emerald-500 mb-2 group-hover:scale-110 transition-transform" size="28"/>
            <span class="text-slate-300 text-sm">Мій розклад</span>
          </router-link>
          <router-link :to="{name: 'patients'}" class="flex flex-col items-center justify-center p-4 bg-slate-950 border border-slate-800 rounded-lg hover:bg-slate-800 transition-colors cursor-pointer group">
            <Users class="text-blue-500 mb-2 group-hover:scale-110 transition-transform" size="28"/>
            <span class="text-slate-300 text-sm">База пацієнтів</span>
          </router-link>
        </div>
      </div>

      <ActivityChart :data="weeklyActivity" title="Активність за тиждень" />
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>