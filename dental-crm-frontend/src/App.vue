<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from './composables/useAuth';
import { usePermissions } from './composables/usePermissions';
import ToastContainer from './components/ToastContainer.vue';
import {
  LayoutDashboard,
  Calendar,
  LayoutGrid,
  Users,
  Stethoscope,
  Building2,
  Package,
  ClipboardList,
  UserCheck,
  Shield,
  Settings,
  LogOut,
  Menu,
  X,
  Sun,
  Moon
} from 'lucide-vue-next';

const { user, logout } = useAuth();
const { isSuperAdmin, isDoctor, canManageCatalog, canManageRoles } = usePermissions();
const router = useRouter();
const route = useRoute();

const isSidebarOpen = ref(true); // Стан меню на десктопі
const isMobileMenuOpen = ref(false); // Для мобілок

const defaultTheme = typeof window !== 'undefined' ? localStorage.getItem('theme') : null;
const theme = ref(defaultTheme || 'dark');
const isDarkTheme = computed(() => theme.value === 'dark');
const themeClass = computed(() => (isDarkTheme.value ? 'dark' : ''));

// Активний клас для меню
const activeClass = "bg-emerald-600 text-text shadow-lg shadow-emerald-500/30";
const inactiveClass = "text-text/70 hover:bg-card/80 hover:text-text";

const handleLogout = async () => {
  await logout();
  router.push({ name: 'login' });
};

const toggleTheme = () => {
  theme.value = isDarkTheme.value ? 'light' : 'dark';
  if (typeof window !== 'undefined') {
    localStorage.setItem('theme', theme.value);
  }
};

// Якщо ми на сторінці логіна - не показуємо лейаут
const isLoginPage = computed(() => route.name === 'login');
</script>

<template>
  <div :class="['min-h-screen font-sans selection:bg-emerald-500/30 bg-bg text-text', themeClass]">
    <!-- Якщо це сторінка логіна - просто рендеримо її на весь екран -->
    <div v-if="isLoginPage" class="min-h-screen">
      <router-view />
    </div>

    <!-- Основний лейаут -->
    <div v-else class="flex min-h-screen">

      <!-- SIDEBAR (Бокова панель) -->
      <aside
          class="fixed inset-y-0 left-0 z-50 w-64 bg-card border-r border-border transition-transform duration-300 lg:translate-x-0 lg:static lg:inset-0 flex flex-col"
          :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
      >
      <!-- Лого -->
      <div class="h-16 flex items-center px-6 border-b border-border">
        <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center mr-3 shadow-lg shadow-emerald-500/20">
          <span class="text-text font-bold text-lg">D</span>
        </div>
        <span class="text-xl font-bold bg-gradient-to-r from-text to-text/60 bg-clip-text text-transparent">DentalCRM</span>

        <!-- Кнопка закриття (мобільна) -->
        <button @click="isMobileMenuOpen = false" class="lg:hidden ml-auto text-text/70">
          <X size="24" />
        </button>
      </div>

      <!-- Меню -->
      <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto custom-scrollbar">

        <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2">Головне</p>

        <router-link
            :to="{name: 'dashboard'}"
            :class="[route.name === 'dashboard' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
        >
          <LayoutDashboard size="20" />
          <span class="font-medium">Дашборд</span>
        </router-link>

        <router-link
            :to="{name: 'schedule'}"
            :class="[route.name === 'schedule' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
        >
          <Calendar size="20" />
          <span class="font-medium">Розклад &amp; Waitlist</span>
        </router-link>

        <!-- ✅ Новий календар у стилі Google Calendar (board/grid) -->
        <router-link
            :to="{name: 'calendar-board'}"
            :class="[route.name === 'calendar-board' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
        >
          <LayoutGrid size="20" />
          <span class="font-medium">Календар (Board)</span>
        </router-link>

        <router-link
            :to="{name: 'patients'}"
            :class="[route.name === 'patients' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
        >
          <Users size="20" />
          <span class="font-medium">Пацієнти</span>
        </router-link>

        <!-- Блок Адміністратора -->
        <div v-if="isSuperAdmin" class="mt-6">
          <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2 mt-6">Управління</p>

          <router-link
              :to="{name: 'clinics'}"
              :class="[route.name === 'clinics' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
          >
            <Building2 size="20" />
            <span class="font-medium">Клініки</span>
          </router-link>

          <router-link
              :to="{name: 'doctors'}"
              :class="[route.name === 'doctors' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
          >
            <Stethoscope size="20" />
            <span class="font-medium">Лікарі</span>
          </router-link>
        </div>

        <div v-if="canManageCatalog" class="mt-6">
          <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2 mt-6">Каталог</p>

          <router-link
            :to="{name: 'equipments'}"
            :class="[route.name === 'equipments' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
          >
            <Package size="20" />
            <span class="font-medium">Обладнання</span>
          </router-link>

          <router-link
            :to="{name: 'procedures'}"
            :class="[route.name === 'procedures' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
          >
            <ClipboardList size="20" />
            <span class="font-medium">Процедури</span>
          </router-link>

          <router-link
            :to="{name: 'assistants'}"
            :class="[route.name === 'assistants' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
          >
            <UserCheck size="20" />
            <span class="font-medium">Асистенти</span>
          </router-link>

          <router-link
            :to="{name: 'clinic-settings'}"
            :class="[route.name === 'clinic-settings' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
          >
            <Settings size="20" />
            <span class="font-medium">Налаштування клініки</span>
          </router-link>
        </div>

        <div v-if="canManageRoles" class="mt-6">
          <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2 mt-6">Доступи</p>

          <router-link
            :to="{name: 'roles'}"
            :class="[route.name === 'roles' ? activeClass : inactiveClass, 'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200']"
          >
            <Shield size="20" />
            <span class="font-medium">Ролі</span>
          </router-link>
        </div>
      </nav>

      <!-- Футер меню -->
      <div class="p-4 border-t border-border">
        <button @click="handleLogout" class="flex items-center gap-3 w-full px-4 py-3 text-text/70 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-colors">
          <LogOut size="20" />
          <span class="font-medium">Вийти</span>
        </button>
      </div>
    </aside>

    <!-- ОСНОВНИЙ КОНТЕНТ -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <!-- Header (Верхня панель) -->
      <header class="h-16 bg-card/50 backdrop-blur-md border-b border-border flex items-center justify-between px-6 sticky top-0 z-40">
        <!-- Кнопка меню (мобільна) -->
        <button @click="isMobileMenuOpen = true" class="lg:hidden text-text/70 hover:text-text">
          <Menu size="24" />
        </button>

        <!-- Хлібні крихти або заголовок (можна додати) -->
        <div class="text-sm text-text/60 hidden sm:block">
          {{ new Date().toLocaleDateString('uk-UA', { weekday: 'long', day: 'numeric', month: 'long' }) }}
        </div>

        <!-- Профіль користувача -->
        <div class="flex items-center gap-4">
          <button
            type="button"
            class="theme-toggle-button"
            :aria-label="isDarkTheme ? 'Увімкнути світлу тему' : 'Увімкнути темну тему'"
            @click="toggleTheme"
          >
            <Sun v-if="!isDarkTheme" size="18" />
            <Moon v-else size="18" />
          </button>
          <div class="text-right hidden sm:block">
            <p class="text-sm font-bold text-text leading-none">{{ user?.first_name }} {{ user?.last_name }}</p>
            <p class="text-xs text-text/60 mt-1">{{ isDoctor ? 'Лікар' : (isSuperAdmin ? 'Адміністратор' : 'Користувач') }}</p>
          </div>
          <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center text-text font-bold shadow-md">
            {{ user?.first_name?.charAt(0) }}
          </div>
        </div>
      </header>

      <!-- Вміст сторінки -->
      <div class="flex-1 overflow-y-auto bg-bg p-4 lg:p-8 custom-scrollbar">
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
          class="fixed inset-0 z-40 bg-text/20 dark:bg-bg/50 backdrop-blur-sm lg:hidden"
      ></div>
    </div>
    <ToastContainer />
  </div>
</template>
