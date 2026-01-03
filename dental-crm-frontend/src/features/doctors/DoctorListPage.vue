<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { UIDrawer, UIButton, UIAvatar, UIBadge, UIDropdown } from '../../ui'
import DoctorQuickView from './components/DoctorQuickView.vue'
import { doctorsApi } from './api'
import type { Doctor } from './types'

const router = useRouter()

const doctors = ref<Doctor[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const selectedDoctorId = ref<number | null>(null)
const drawerOpen = ref(false)
const search = ref('')

const filteredDoctors = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return doctors.value
  return doctors.value.filter(
    (doctor) =>
      doctor.full_name?.toLowerCase().includes(term) ||
      doctor.specialization?.toLowerCase().includes(term) ||
      doctor.clinic?.name?.toLowerCase().includes(term)
  )
})

const activeDoctor = computed(() =>
  filteredDoctors.value.find((d) => d.id === selectedDoctorId.value) || null
)

const quickActions = [
  { id: 'schedule', label: 'Запланувати прийом', icon: '📅' },
  { id: 'reminder', label: 'Додати нагадування', icon: '⏰' },
  { id: 'email', label: 'Надіслати email', icon: '✉️' },
  { id: 'message', label: 'Повідомлення (Telegram/Email)', icon: '💬' },
  { id: 'call', label: 'Дзвінок', icon: '📞' }
]

const fetchDoctors = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await doctorsApi.list()
    doctors.value = data?.data ?? data ?? []
  } catch (e: any) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Не вдалося завантажити лікарів'
  } finally {
    loading.value = false
  }
}

onMounted(fetchDoctors)

const openDoctor = (doctor: Doctor) => {
  selectedDoctorId.value = doctor.id
  drawerOpen.value = true
}

const handleManageClick = (doctor: Doctor, event: Event) => {
  event.stopPropagation()
  openDoctor(doctor)
}

const goToDetails = (id: number) => {
  router.push({ name: 'doctor-details', params: { id } })
}

const statusVariant = (doctor: Doctor) => {
  if (doctor.status === 'vacation') return 'warning'
  if (doctor.is_active === false || doctor.status === 'inactive') return 'neutral'
  return 'success'
}

const handleAction = (actionId: string) => {
  // Stub: integrate with real flows later
  console.log(`Action: ${actionId} for doctor #${selectedDoctorId.value}`)
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-text">Список лікарів</h1>
      </div>
      <UIButton variant="secondary" size="sm" @click="fetchDoctors">Оновити</UIButton>
    </header>

    <section class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4">
      <div class="flex flex-wrap items-center gap-3">
        <input
          v-model="search"
          type="search"
          placeholder="Пошук (ПІБ, спец, клініка)"
          class="w-full sm:w-80 rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
        />
      </div>

      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-rose-400">❌ {{ error }}</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
              <th class="px-4 py-2">ПІБ</th>
              <th class="px-4 py-2">Клініка</th>
              <th class="px-4 py-2">Спеціалізація</th>
              <th class="px-4 py-2">Статус</th>
              <th class="px-4 py-2 text-right">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="doctor in filteredDoctors"
              :key="doctor.id"
              class="border-t border-border/60 hover:bg-card/80 cursor-pointer transition-colors"
              :class="doctor.id === selectedDoctorId ? 'bg-emerald-500/5' : ''"
              @click="openDoctor(doctor)"
            >
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <UIAvatar :src="doctor.avatar_url || ''" :fallback-text="doctor.full_name?.[0] || '?'" size="sm" />
                  <div>
                    <p class="font-semibold text-text">{{ doctor.full_name }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-text/80">
                {{ doctor.clinic?.name || '—' }}
              </td>
              <td class="px-4 py-3 text-text/80">
                {{ doctor.specialization || '—' }}
              </td>
              <td class="px-4 py-3">
                <UIBadge :variant="statusVariant(doctor)" small>
                  {{
                    doctor.status === 'vacation'
                      ? 'Відпустка'
                      : doctor.is_active === false || doctor.status === 'inactive'
                        ? 'Неактивний'
                        : 'Активний'
                  }}
                </UIBadge>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <UIDropdown :items="quickActions" placement="bottom-end" @select="handleAction">
                    <template #trigger="{ toggle }">
                      <UIButton variant="secondary" size="sm" @click.stop="toggle">Дії ▾</UIButton>
                    </template>
                  </UIDropdown>
                  <UIButton variant="primary" size="sm" @click.stop="handleManageClick(doctor, $event)">Керувати</UIButton>
                  <UIButton variant="ghost" size="sm" @click.stop="goToDetails(doctor.id)">Details</UIButton>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredDoctors.length">
              <td colspan="5" class="px-4 py-4 text-sm text-text/70">Нічого не знайдено.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <UIDrawer v-model="drawerOpen" title="Доктор" width="520px" @close="selectedDoctorId = null">
      <DoctorQuickView
        :doctor="activeDoctor"
        @close="drawerOpen = false"
        @details="goToDetails"
        @action="({ id }) => handleAction(id)"
      />
    </UIDrawer>
  </div>
</template>

