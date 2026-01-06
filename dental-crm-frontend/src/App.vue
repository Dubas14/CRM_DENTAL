// @ts-nocheck
<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from './composables/useAuth'
import { usePermissions } from './composables/usePermissions'
import ToastContainer from './components/ToastContainer.vue'
import { useThemeStore } from './stores/theme'
// @ts-ignore
import UserProfileModal from './components/UserProfileModal'
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
  GraduationCap,
  Warehouse
} from 'lucide-vue-next'

const { user, logout, initAuth } = useAuth()
const { isSuperAdmin, isClinicAdmin, isDoctor, canManageCatalog, canManageRoles, hasPermission } =
  usePermissions()
const router = useRouter()
const route = useRoute()
const themeStore = useThemeStore()

// isSidebarOpen removed
const isMobileMenuOpen = ref(false) // Для мобілок
const showProfileMenu = ref(false)
const showProfileModal = ref(false)

const themeOptions = [
  { value: 'light', label: 'Світла', icon: '🌞' },
  { value: 'dark', label: 'Темна', icon: '🌙' },
  { value: 'clinic', label: 'Clinic', icon: '🏥' }
]

const weekdays = ['неділя', 'понеділок', 'вівторок', 'середа', 'четвер', 'пʼятниця', 'субота']
const months = [
  'Січня',
  'Лютого',
  'Березня',
  'Квітня',
  'Травня',
  'Червня',
  'Липня',
  'Серпня',
  'Вересня',
  'Жовтня',
  'Листопада',
  'Грудня'
]

const todayLabel = computed(() => {
  const d = new Date()
  const weekday = weekdays[d.getDay()] || ''
  const month = months[d.getMonth()] || ''
  return `${weekday}, ${d.getDate()} ${month}`
})

// Активний клас для меню
const activeClass = 'bg-emerald-600 text-text shadow-lg shadow-emerald-500/30'
const inactiveClass = 'text-text/70 hover:bg-card/80 hover:text-text'

const handleLogout = async () => {
  await logout()
  router.push({ name: 'login' })
}

const closeOnOutside = (event: MouseEvent) => {
  const menu = document.getElementById('profile-menu')
  const trigger = document.getElementById('profile-trigger')
  if (menu && trigger) {
    if (!menu.contains(event.target as Node) && !trigger.contains(event.target as Node)) {
      showProfileMenu.value = false
    }
  }
}

const closeOnEsc = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    showProfileMenu.value = false
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('click', closeOnOutside)
  window.addEventListener('keydown', closeOnEsc)
}

// Якщо ми на сторінці логіна - не показуємо лейаут
const isLoginPage = computed(() => route.name === 'login')
const isCalendarBoard = computed(() => route.name === 'calendar-board')

const avatarUrl = computed(() => user.value?.avatar_url || user.value?.doctor?.avatar_url || null)
const userInitials = computed(() => (user.value?.first_name || user.value?.name || 'U')[0])

const closeProfileMenu = () => (showProfileMenu.value = false)

const onProfileUpdated = async () => {
  closeProfileMenu()
  showProfileModal.value = false
  await initAuth()
}
</script>

<template>
  <div class="h-full font-sans selection:bg-emerald-500/30 bg-bg text-text">
    <!-- Якщо це сторінка логіна - просто рендеримо її на весь екран -->
    <div v-if="isLoginPage" class="min-h-screen">
      <router-view />
    </div>

    <!-- Основний лейаут -->
    <div v-else class="flex h-full min-h-0">
      <!-- SIDEBAR (Бокова панель) -->
      <aside
        class="fixed inset-y-0 left-0 z-50 w-64 bg-card border-r border-border transition-transform duration-300 lg:translate-x-0 lg:static lg:inset-0 flex flex-col"
        :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <!-- Лого -->
        <div class="h-16 flex items-center px-6 border-b border-border">
          <div
            class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center mr-3 shadow-lg shadow-emerald-500/20"
          >
            <span class="text-text font-bold text-lg">D</span>
          </div>
          <span
            class="text-xl font-bold bg-gradient-to-r from-text to-text/60 bg-clip-text text-transparent"
            >DentalCRM</span
          >

          <!-- Кнопка закриття (мобільна) -->
          <button @click="isMobileMenuOpen = false" class="lg:hidden ml-auto text-text/70">
            <X :size="24" />
          </button>
        </div>

        <!-- Меню -->
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto custom-scrollbar">
          <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2">Головне</p>

          <router-link
            :to="{ name: 'dashboard' }"
            :class="[
              route.name === 'dashboard' ? activeClass : inactiveClass,
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
            ]"
          >
            <LayoutDashboard :size="20" />
            <span class="font-medium">Дашборд</span>
          </router-link>

          <router-link
            :to="{ name: 'schedule' }"
            :class="[
              route.name === 'schedule' ? activeClass : inactiveClass,
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
            ]"
          >
            <Calendar :size="20" />
            <span class="font-medium">Розклад &amp; Waitlist</span>
          </router-link>

          <!-- ✅ Новий календар у стилі Google Calendar (board/grid) -->
          <router-link
            :to="{ name: 'calendar-board' }"
            :class="[
              route.name === 'calendar-board' ? activeClass : inactiveClass,
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
            ]"
          >
            <LayoutGrid :size="20" />
            <span class="font-medium">Календар (Board)</span>
          </router-link>

          <router-link
            :to="{ name: 'patients' }"
            :class="[
              route.name === 'patients' ? activeClass : inactiveClass,
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
            ]"
          >
            <Users :size="20" />
            <span class="font-medium">Пацієнти</span>
          </router-link>

          <!-- Блок Адміністратора -->
          <div
            v-if="
              isSuperAdmin ||
              isClinicAdmin ||
              hasPermission('clinic.view') ||
              hasPermission('user.view')
            "
            class="mt-6"
          >
            <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2 mt-6">
              Управління
            </p>

            <router-link
              :to="{ name: 'clinics' }"
              :class="[
                route.name === 'clinics' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <Building2 :size="20" />
              <span class="font-medium">Клініки</span>
            </router-link>

            <router-link
              :to="{ name: 'doctors' }"
              :class="[
                route.name === 'doctors' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <Stethoscope :size="20" />
              <span class="font-medium">Лікарі</span>
            </router-link>
          </div>

          <div
            v-if="
              isSuperAdmin ||
              isClinicAdmin ||
              canManageCatalog ||
              hasPermission('inventory.view') ||
              hasPermission('inventory.manage') ||
              hasPermission('procedure.view') ||
              hasPermission('procedure.manage') ||
              hasPermission('equipment.view') ||
              hasPermission('equipment.manage') ||
              hasPermission('clinic.view')
            "
            class="mt-6"
          >
            <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2 mt-6">
              Каталог
            </p>

            <router-link
              :to="{ name: 'equipments' }"
              :class="[
                route.name === 'equipments' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <Package :size="20" />
              <span class="font-medium">Обладнання</span>
            </router-link>

            <router-link
              :to="{ name: 'procedures' }"
              :class="[
                route.name === 'procedures' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <ClipboardList :size="20" />
              <span class="font-medium">Процедури</span>
            </router-link>

            <router-link
              :to="{ name: 'specializations' }"
              :class="[
                route.name === 'specializations' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <GraduationCap :size="20" />
              <span class="font-medium">Спеціалізації</span>
            </router-link>

            <router-link
              :to="{ name: 'inventory' }"
              :class="[
                route.name === 'inventory' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <Warehouse :size="20" />
              <span class="font-medium">Склад</span>
            </router-link>

            <router-link
              :to="{ name: 'assistants' }"
              :class="[
                route.name === 'assistants' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <UserCheck :size="20" />
              <span class="font-medium">Асистенти</span>
            </router-link>

            <router-link
              :to="{ name: 'clinic-settings' }"
              :class="[
                route.name === 'clinic-settings' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <Settings :size="20" />
              <span class="font-medium">Налаштування клініки</span>
            </router-link>
          </div>

          <div v-if="canManageRoles" class="mt-6">
            <p class="px-4 text-xs font-bold text-text/60 uppercase tracking-wider mb-2 mt-6">
              Доступи
            </p>

            <router-link
              :to="{ name: 'employees' }"
              :class="[
                route.name === 'employees' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <Shield :size="20" />
              <span class="font-medium">Співробітники</span>
            </router-link>

            <router-link
              :to="{ name: 'role-manager' }"
              :class="[
                route.name === 'role-manager' ? activeClass : inactiveClass,
                'flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200'
              ]"
            >
              <Shield :size="20" />
              <span class="font-medium">Налаштування Ролей</span>
            </router-link>
          </div>
        </nav>

        <!-- Футер меню -->
        <div class="p-4 border-t border-border">
          <button
            @click="handleLogout"
            class="flex items-center gap-3 w-full px-4 py-3 text-text/70 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-colors"
          >
            <LogOut :size="20" />
            <span class="font-medium">Вийти</span>
          </button>
        </div>
      </aside>

      <!-- ОСНОВНИЙ КОНТЕНТ -->
      <main class="flex-1 flex min-w-0 flex-col overflow-hidden">
        <!-- Header (Верхня панель) -->
        <header
          class="h-16 bg-bg-surface/70 backdrop-blur border-b border-border-soft flex items-center justify-between px-6 sticky top-0 z-40"
        >
          <!-- Кнопка меню (мобільна) -->
          <button @click="isMobileMenuOpen = true" class="lg:hidden text-text/70 hover:text-text">
            <Menu :size="24" />
          </button>

          <!-- Хлібні крихти або заголовок (можна додати) -->
          <div class="text-sm text-text/60 hidden sm:block">
            {{ todayLabel }}
          </div>

          <!-- Профіль користувача -->
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
              <span class="text-xs text-text/60 hidden md:inline">Тема</span>
              <div class="flex items-center gap-2 rounded-full border border-border bg-card/70 px-2 py-1">
                <button
                  v-for="option in themeOptions"
                  :key="option.value"
                  type="button"
                  class="group relative flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium transition-colors"
                  :class="
                    themeStore.theme === option.value
                      ? 'bg-accent/20 text-accent shadow-sm shadow-black/10 dark:shadow-black/40'
                      : 'text-text/70 hover:text-text hover:bg-card/80'
                  "
                  @click="themeStore.setTheme(option.value)"
                  :title="option.label"
                >
                  <span aria-hidden="true">{{ option.icon }}</span>
                  <span class="sr-only">{{ option.label }}</span>
                </button>
              </div>
            </div>

            <div class="relative">
              <button
                class="flex items-center gap-3 rounded-full border border-border/60 bg-card/70 px-2 py-1 hover:bg-card/90 transition"
                @click="showProfileMenu = !showProfileMenu"
                @keydown.esc="closeProfileMenu"
                id="profile-trigger"
              >
                <div class="text-right hidden sm:block">
                  <p class="text-sm font-bold text-text leading-none">
                    {{ user?.first_name }} {{ user?.last_name }}
                  </p>
                  <p class="text-xs text-text/60 mt-1">
                    {{ isDoctor ? 'Лікар' : isSuperAdmin ? 'Адміністратор' : 'Користувач' }}
                  </p>
                </div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center text-text font-bold shadow-md overflow-hidden">
                  <img v-if="avatarUrl" :src="avatarUrl" alt="avatar" class="w-full h-full object-cover" />
                  <span v-else>{{ userInitials }}</span>
                </div>
              </button>

              <div
                v-if="showProfileMenu"
                class="absolute right-0 mt-2 w-52 rounded-xl border border-border bg-card shadow-xl divide-y divide-border z-50"
                id="profile-menu"
              >
                <button
                  class="w-full text-left px-4 py-3 hover:bg-card/80 text-sm"
                  @click.stop="showProfileModal = true; closeProfileMenu()"
                >
                  Редагувати профіль
                </button>
                <button class="w-full text-left px-4 py-3 hover:bg-card/80 text-sm text-rose-400" @click.stop="handleLogout(); closeProfileMenu()">
                  Вийти
                </button>
              </div>
            </div>
          </div>
        </header>

        <!-- Вміст сторінки -->
        <div
          class="flex-1 min-h-0 bg-bg"
          :class="
            isCalendarBoard ? 'overflow-hidden' : 'overflow-y-auto p-4 lg:p-8 custom-scrollbar'
          "
        >
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
    <UserProfileModal v-model="showProfileModal" :user="user" @updated="onProfileUpdated" />
  </div>
</template>
