<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { debounce } from 'lodash-es'
import roleApi from '../services/roleApi'
import EmployeeEditModal from '../components/employees/EmployeeEditModal.vue'
import { UIButton, UIBadge } from '../ui'
import { useToast } from '../composables/useToast'

const { showToast } = useToast()

const users = ref([])
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const currentPage = ref(1)
const showEditModal = ref(false)
const modalUser = ref<any>(null)
const perPage = 12
const totalPages = ref(1)
const totalItems = ref(0)
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  total: 0,
  perPage,
  from: 0,
  to: 0
})

const safeCurrentPage = computed(() =>
  Math.min(Math.max(currentPage.value, 1), totalPages.value || 1)
)

const pagesToShow = computed(() => {
  const visible = 5
  const half = Math.floor(visible / 2)
  let start = Math.max(1, safeCurrentPage.value - half)
  const end = Math.min(totalPages.value, start + visible - 1)

  if (end - start + 1 < visible) {
    start = Math.max(1, end - visible + 1)
  }

  return Array.from({ length: end - start + 1 }, (_, idx) => start + idx)
})

const displayFrom = computed(() => {
  if (!totalItems.value) return 0
  return pagination.value.from ?? (safeCurrentPage.value - 1) * perPage + 1
})

const displayTo = computed(() => {
  if (!totalItems.value) return 0
  return pagination.value.to ?? Math.min(safeCurrentPage.value * perPage, totalItems.value)
})

const fetchUsers = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await roleApi.listUsers({
      page: currentPage.value,
      per_page: perPage,
      search: searchQuery.value || undefined
    })
    users.value = data.data ?? data
    const meta = data.meta ?? data ?? {}
    totalPages.value = meta.last_page ?? 1
    totalItems.value = meta.total ?? users.value.length
    currentPage.value = meta.current_page ?? currentPage.value
    pagination.value = {
      currentPage: currentPage.value,
      lastPage: totalPages.value,
      total: totalItems.value,
      perPage,
      from: meta.from ?? (users.value.length ? (currentPage.value - 1) * perPage + 1 : 0),
      to:
        meta.to ??
        (users.value.length ? Math.min(currentPage.value * perPage, totalItems.value) : 0)
    }
  } catch (err) {
    console.error(err)
    error.value = 'Не вдалося завантажити співробітників'
  } finally {
    loading.value = false
  }
}

const displayName = (user: any) => {
  if (user.first_name || user.last_name) {
    return `${user.first_name || ''} ${user.last_name || ''}`.trim()
  }
  return user.name || user.email
}

const getUserRole = (user: any) => {
  if (user.roles && user.roles.length > 0) {
    return user.roles[0].name
  }
  return '—'
}

const getRoleBadgeVariant = (roleName: string) => {
  const variants: Record<string, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    super_admin: 'danger',
    clinic_admin: 'warning',
    doctor: 'success',
    assistant: 'info',
    registrar: 'primary'
  }
  return variants[roleName] || 'info'
}

const openEditModal = (user: any) => {
  modalUser.value = user
  showEditModal.value = true
}

const onModalSaved = () => {
  fetchUsers()
  showEditModal.value = false
  modalUser.value = null
}

onMounted(async () => {
  await fetchUsers()
})

const debouncedSearch = debounce(() => {
  currentPage.value = 1
  fetchUsers()
}, 300)

const onSearchInput = () => {
  debouncedSearch()
}

const goToPage = (page: number) => {
  const nextPage = Math.min(Math.max(page, 1), totalPages.value)
  if (nextPage === currentPage.value) return
  currentPage.value = nextPage
  fetchUsers()
}
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold">Співробітники</h1>
      <p class="text-sm text-text/70">Управління співробітниками та їх ролями</p>
    </header>

    <section
      class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[220px]">
          <label
            for="employees-search"
            class="block text-xs uppercase tracking-wide text-text/70 mb-1"
          >
            Пошук співробітника
          </label>
          <input
            v-model="searchQuery"
            id="employees-search"
            name="search"
            type="text"
            placeholder="Імʼя, прізвище або email"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text/90"
            @input="onSearchInput"
          />
        </div>
        <div class="text-xs text-text/60">
          Показано {{ displayFrom }}–{{ displayTo }} з {{ totalItems }} співробітників
        </div>
      </div>
      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="!users.length" class="text-sm text-text/70">Немає співробітників.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-text/70 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Користувач</th>
              <th class="text-left py-2 px-3">Email</th>
              <th class="text-left py-2 px-3">Роль</th>
              <th class="text-left py-2 px-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-t border-border">
              <td class="py-2 px-3 text-text/90">{{ displayName(user) }}</td>
              <td class="py-2 px-3 text-text/70">{{ user.email }}</td>
              <td class="py-2 px-3">
                <UIBadge :variant="getRoleBadgeVariant(getUserRole(user))" small>
                  {{ getUserRole(user) }}
                </UIBadge>
              </td>
              <td class="py-2 px-3 text-right">
                <UIButton variant="ghost" size="sm" @click="openEditModal(user)">
                  Редагувати
                </UIButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div
        v-if="totalPages > 1"
        class="flex flex-wrap items-center justify-between gap-3 text-sm text-text/70"
      >
        <p>Показано {{ displayFrom }}–{{ displayTo }} з {{ totalItems }}</p>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-1.5 text-sm text-text transition hover:bg-card/70 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="safeCurrentPage === 1"
            @click="goToPage(safeCurrentPage - 1)"
          >
            Попередня
          </button>

          <button
            v-for="page in pagesToShow"
            :key="page"
            type="button"
            class="inline-flex min-w-[40px] items-center justify-center rounded-lg border px-3 py-1.5 text-sm transition"
            :class="
              page === safeCurrentPage
                ? 'border-accent bg-accent text-card'
                : 'border-border bg-card text-text hover:bg-card/70'
            "
            @click="goToPage(page)"
          >
            {{ page }}
          </button>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-1.5 text-sm text-text transition hover:bg-card/70 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="safeCurrentPage === totalPages"
            @click="goToPage(safeCurrentPage + 1)"
          >
            Наступна
          </button>
        </div>
      </div>
    </section>

    <EmployeeEditModal v-model="showEditModal" :user="modalUser" @saved="onModalSaved" />
  </div>
</template>
