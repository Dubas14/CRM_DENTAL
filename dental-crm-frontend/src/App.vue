<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from './composables/useAuth';
import { usePermissions } from './composables/usePermissions';
import ToastContainer from './components/ToastContainer.vue';
import {
  LayoutDashboard,
  Calendar,
  Users,
  Stethoscope,
  Building2,
  LogOut,
  Menu,
  X
} from 'lucide-vue-next';

const { user, logout } = useAuth();
const { isSuperAdmin, isDoctor } = usePermissions();
const router = useRouter();
const route = useRoute();

const isSidebarOpen = ref(true); // Стан меню на десктопі
const isMobileMenuOpen = ref(false); // Для мобілок

// Активний клас для меню
const activeClass = "bg-emerald-600 text-white shadow-lg shadow-emerald-500/30";
const inactiveClass = "text-slate-400 hover:bg-slate-800 hover:text-slate-100";

const handleLogout = async () => {
  await logout();
  router.push({ name: 'login' });
};

// Якщо ми на сторінці логіна - не показуємо лейаут
const isLoginPage = computed(() => route.name === 'login');
</script>

<template>
  <!-- Якщо це сторінка логіна - просто рендеримо її на весь екран -->
  <div v-if="isLoginPage" class="bg-slate-950 min-h-screen text-slate-200">
    <router-view />
  </div>

  <!-- Основний лейаут -->
  <div v-else class="flex min-h-screen bg-slate-950 text-slate-200 font-sans selection:bg-emerald-500/30">

    <!-- SIDEBAR (Бокова панель) -->
    <aside
        class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 transition-transform duration-300 lg:translate-x-0 lg:static lg:inset-0 flex flex-col"
        :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Лого -->
      <div class="h-16 flex items-center px-6 border-b border-slate-800">
        <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center mr-3 shadow-lg shadow-emerald-500/20">
          <span class="text-white font-bold text-lg">D</span>
        </div>
        <span class="text-xl font-bold bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">DentalCRM</span>

        <!-- Кнопка закриття (мобільна) -->
        <button @click="isMobileMenuOpen = false" class="lg:hidden ml-auto text-slate-400">
          <X size="24" />
        </button>
      </div>

      <!-- Меню -->
      <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto custom-scrollbar">

        <p class="px-4 text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Головне</p>

        <router-link :to="{name: 'dashboard'}" :class="[route.name === 'dashboard' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']">
          <LayoutDashboard size="20" />
          <span class="font-medium">Дашборд</span>
        </router-link>

        <router-link :to="{name: 'schedule'}" :class="[route.name === 'schedule' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']">
          <Calendar size="20" />
          <span class="font-medium">Розклад &amp; Waitlist</span>
        </router-link>

        <router-link :to="{name: 'patients'}" :class="[route.name === 'patients' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']">
          <Users size="20" />
          <span class="font-medium">Пацієнти</span>
        </router-link>

        <!-- Блок Адміністратора -->
        <div v-if="isSuperAdmin" class="mt-6">
          <p class="px-4 text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 mt-6">Управління</p>

          <router-link :to="{name: 'clinics'}" :class="[route.name === 'clinics' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']">
            <Building2 size="20" />
            <span class="font-medium">Клініки</span>
          </router-link>

          <router-link :to="{name: 'doctors'}" :class="[route.name === 'doctors' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']">
            <Stethoscope size="20" />
            <span class="font-medium">Лікарі</span>
          </router-link>
        </div>
      </nav>

      <!-- Футер меню -->
      <div class="p-4 border-t border-slate-800">
        <button @click="handleLogout" class="flex items-center gap-3 w-full px-4 py-3 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-colors">
          <LogOut size="20" />
          <span class="font-medium">Вийти</span>
        </button>
      </div>
    </aside>

    <!-- ОСНОВНИЙ КОНТЕНТ -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <!-- Header (Верхня панель) -->
      <header class="h-16 bg-slate-900/50 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-6 sticky top-0 z-40">
        <!-- Кнопка меню (мобільна) -->
        <button @click="isMobileMenuOpen = true" class="lg:hidden text-slate-400 hover:text-white">
          <Menu size="24" />
        </button>

        <!-- Хлібні крихти або заголовок (можна додати) -->
        <div class="text-sm text-slate-500 hidden sm:block">
          {{ new Date().toLocaleDateString('uk-UA', { weekday: 'long', day: 'numeric', month: 'long' }) }}
        </div>

        <!-- Профіль користувача -->
        <div class="flex items-center gap-4">
          <div class="text-right hidden sm:block">
            <p class="text-sm font-bold text-white leading-none">{{ user?.first_name }} {{ user?.last_name }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ isDoctor ? 'Лікар' : (isSuperAdmin ? 'Адміністратор' : 'Користувач') }}</p>
          </div>
          <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center text-white font-bold shadow-md">
            {{ user?.first_name?.charAt(0) }}
          </div>
        </div>
      </header>

      <!-- Вміст сторінки -->
      <div class="flex-1 overflow-y-auto bg-slate-950 p-4 lg:p-8 custom-scrollbar">
        <!-- Анімація переходів -->
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </div>

    </main>

    <!-- Затемнення для мобільного меню -->
    <div
        v-if="isMobileMenuOpen"
        @click="isMobileMenuOpen = false"
        class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
    ></div>
  </div>
  <ToastContainer />
</template>

<style>
/* Глобальні стилі для скролбару */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #0f172a;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #475569;
}

/* Анімація переходу сторінок */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>