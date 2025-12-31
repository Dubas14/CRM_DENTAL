<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../services/apiClient'
import { useAuth } from '../composables/useAuth'

const route = useRoute()
const router = useRouter()
const { user } = useAuth()

const doctorId = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const saveError = ref(null)
const savedMessage = ref('')

const doctor = ref(null)

const procedures = ref([])
const proceduresLoading = ref(false)
const proceduresSaving = ref(false)
const proceduresError = ref('')
const proceduresSavedMessage = ref('')

const form = ref({
  full_name: '',
  specialization: '',
  bio: '',
  color: '#22c55e',
  is_active: true
})

const canEdit = computed(() => {
  if (!user.value) return false
  if (user.value.global_role === 'super_admin') return true
  // якщо це сам лікар
  if (doctor.value?.user_id === user.value.id) return true
  // далі можна додати перевірку clinic_admin, коли підключимо ролі клінік на фронт
  return false
})

const loadDoctor = async () => {
  loading.value = true
  error.value = null

  try {
    const { data } = await apiClient.get(`/doctors/${doctorId.value}`)
    doctor.value = data

    form.value = {
      full_name: data.full_name || '',
      specialization: data.specialization || '',
      bio: data.bio || '',
      color: data.color || '#22c55e',
      is_active: !!data.is_active
    }
  } catch (e) {
    console.error(e)
    error.value = e.response?.data?.message || 'Не вдалося завантажити лікаря'
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  if (!doctor.value) return
  form.value = {
    full_name: doctor.value.full_name || '',
    specialization: doctor.value.specialization || '',
    bio: doctor.value.bio || '',
    color: doctor.value.color || '#22c55e',
    is_active: !!doctor.value.is_active
  }
  saveError.value = ''
  savedMessage.value = ''
}

const loadDoctorProcedures = async () => {
  proceduresLoading.value = true
  proceduresError.value = ''

  try {
    const { data } = await apiClient.get(`/doctors/${doctorId.value}/procedures`)
    procedures.value = Array.isArray(data) ? data : []
  } catch (e) {
    console.error(e)
    proceduresError.value = e.response?.data?.message || 'Не вдалося завантажити процедури'
  } finally {
    proceduresLoading.value = false
  }
}

const saveDoctorProcedures = async () => {
  if (!canEdit.value) return

  proceduresSaving.value = true
  proceduresError.value = ''
  proceduresSavedMessage.value = ''

  try {
    const payload = {
      procedures: procedures.value.map((proc) => ({
        procedure_id: proc.id,
        is_assigned: !!proc.is_assigned,
        custom_duration_minutes:
          proc.custom_duration_minutes !== '' && proc.custom_duration_minutes !== null
            ? Number(proc.custom_duration_minutes)
            : null
      }))
    }

    await apiClient.put(`/doctors/${doctorId.value}/procedures`, payload)
    proceduresSavedMessage.value = 'Процедури оновлено'
    await loadDoctorProcedures()
  } catch (e) {
    console.error(e)
    if (e.response?.data?.errors) {
      const firstKey = Object.keys(e.response.data.errors)[0]
      proceduresError.value = e.response.data.errors[firstKey][0]
    } else {
      proceduresError.value = e.response?.data?.message || 'Помилка збереження процедур'
    }
  } finally {
    proceduresSaving.value = false
  }
}

const saveDoctor = async () => {
  if (!canEdit.value) return

  saving.value = true
  saveError.value = ''
  savedMessage.value = ''

  try {
    const payload = { ...form.value }
    const { data } = await apiClient.put(`/doctors/${doctorId.value}`, payload)
    doctor.value = data

    // 🔹 Логічна поведінка:
    //   - super_admin → назад у список лікарів
    //   - інші (сам лікар у майбутньому) → залишаємо на сторінці з повідомленням
    if (user.value?.global_role === 'super_admin') {
      await router.push({ name: 'doctors' })
    } else {
      savedMessage.value = 'Зміни збережено'
    }
  } catch (e) {
    console.error(e)
    if (e.response?.data?.errors) {
      const firstKey = Object.keys(e.response.data.errors)[0]
      saveError.value = e.response.data.errors[firstKey][0]
    } else {
      saveError.value = e.response?.data?.message || 'Помилка збереження'
    }
  } finally {
    saving.value = false
  }
}

const goToSchedule = () => {
  router.push({ name: 'schedule', query: { doctor: doctorId.value } })
}

onMounted(loadDoctor)
onMounted(loadDoctorProcedures)
</script>

<template>
  <div class="space-y-6">
    <button type="button" class="text-xs text-text/70 hover:text-text/90" @click="$router.back()">
      ← Назад до списку лікарів
    </button>

    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ doctor?.full_name || 'Лікар' }}
        </h1>
        <p class="text-sm text-text/70">Керування профілем лікаря та переходом до розкладу.</p>
      </div>

      <button
        type="button"
        class="px-4 py-2 rounded-lg border border-border/80 text-sm text-text/90 hover:bg-card/80"
        @click="goToSchedule"
      >
        Перейти до розкладу
      </button>
      <button
        type="button"
        class="px-4 py-2 rounded-lg border border-border/80 text-sm text-text/90 hover:bg-card/80"
        @click="$router.push({ name: 'doctor-weekly-schedule', params: { id: doctorId } })"
      >
        Налаштувати тижневий розклад
      </button>
    </div>

    <div v-if="loading" class="text-sm text-text/70">Завантаження даних лікаря...</div>

    <div v-else-if="error" class="text-sm text-red-400">❌ {{ error }}</div>

    <div v-else class="grid gap-6 md:grid-cols-[2fr,1fr]">
      <!-- Профіль лікаря -->
      <section
        class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
      >
        <h2 class="text-sm font-semibold text-text/90 mb-2">Анкетні дані</h2>

        <div v-if="saveError" class="text-sm text-red-400">❌ {{ saveError }}</div>
        <div v-if="savedMessage" class="text-sm text-emerald-400">✅ {{ savedMessage }}</div>

        <div class="space-y-4">
          <div>
            <label for="doctor-details-full-name" class="block text-xs uppercase text-text/70 mb-1">
              ПІБ
            </label>
            <input
              v-model="form.full_name"
              id="doctor-details-full-name"
              name="full_name"
              :disabled="!canEdit"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-70"
            />
          </div>

          <div>
            <label
              for="doctor-details-specialization"
              class="block text-xs uppercase text-text/70 mb-1"
            >
              Спеціалізація
            </label>
            <input
              v-model="form.specialization"
              id="doctor-details-specialization"
              name="specialization"
              :disabled="!canEdit"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-70"
            />
          </div>

          <div>
            <label for="doctor-details-bio" class="block text-xs uppercase text-text/70 mb-1">
              Коротке біо
            </label>
            <textarea
              v-model="form.bio"
              id="doctor-details-bio"
              name="bio"
              :disabled="!canEdit"
              rows="3"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-70"
            />
          </div>

          <div class="flex flex-wrap items-center gap-4">
            <div>
              <label for="doctor-details-color" class="block text-xs uppercase text-text/70 mb-1">
                Колір картки
              </label>
              <input
                v-model="form.color"
                id="doctor-details-color"
                name="color"
                :disabled="!canEdit"
                type="color"
                class="h-10 w-20 rounded-lg bg-bg border border-border/80"
              />
            </div>
            <div class="flex items-center gap-2 mt-4">
              <input
                id="doctor-details-active"
                name="is_active"
                v-model="form.is_active"
                :disabled="!canEdit"
                type="checkbox"
                class="h-4 w-4 rounded border-border/70 bg-card"
              />
              <label for="doctor-details-active" class="text-sm text-text/90"> Активний лікар </label>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-4" v-if="canEdit">
          <button
            type="button"
            class="px-3 py-2 rounded-lg border border-border/80 text-sm text-text/80 hover:bg-card/80"
            @click="resetForm"
          >
            Скасувати
          </button>
          <button
            type="button"
            :disabled="saving"
            class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-text hover:bg-emerald-400 disabled:opacity-60"
            @click="saveDoctor"
          >
            {{ saving ? 'Збереження...' : 'Зберегти' }}
          </button>
        </div>
      </section>

      <!-- Інфо про акаунт -->
      <section
        class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-3"
      >
        <h2 class="text-sm font-semibold text-text/90">Акаунт користувача</h2>
        <p class="text-xs text-text/70">Цей лікар прив’язаний до користувача системи.</p>
        <div class="space-y-2 text-sm">
          <div>
            <span class="text-text/70">Email (логін): </span>
            <span class="text-text">
              {{ doctor?.user?.email || '—' }}
            </span>
          </div>
          <div>
            <span class="text-text/70">Клініка: </span>
            <span class="text-text">
              {{ doctor?.clinic?.name || '—' }}
            </span>
          </div>
        </div>
      </section>
    </div>

    <section
      class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-sm font-semibold text-text/90">Процедури лікаря</h2>
          <p class="text-xs text-text/70">
            Оберіть доступні процедури та задайте персональну тривалість (за потреби).
          </p>
        </div>
        <button
          type="button"
          :disabled="proceduresSaving || !canEdit"
          class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-text hover:bg-emerald-400 disabled:opacity-60"
          @click="saveDoctorProcedures"
        >
          {{ proceduresSaving ? 'Збереження...' : 'Зберегти процедури' }}
        </button>
      </div>

      <div v-if="proceduresError" class="text-sm text-red-400">❌ {{ proceduresError }}</div>
      <div v-if="proceduresSavedMessage" class="text-sm text-emerald-400">
        ✅ {{ proceduresSavedMessage }}
      </div>

      <div v-if="proceduresLoading" class="text-sm text-text/70">Завантаження процедур...</div>

      <div v-else-if="!procedures.length" class="text-sm text-text/70">
        Процедури для цієї клініки відсутні.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase text-text/60 border-b border-border">
              <th class="py-2 pr-2">Активна</th>
              <th class="py-2 pr-2">Процедура</th>
              <th class="py-2 pr-2">Категорія</th>
              <th class="py-2 pr-2">Базова тривалість</th>
              <th class="py-2 pr-2">Персональна тривалість</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="procedure in procedures" :key="procedure.id" class="border-b border-border">
              <td class="py-2 pr-2">
                <label :for="`doctor-procedure-assigned-${procedure.id}`" class="sr-only">
                  Активна
                </label>
                <input
                  v-model="procedure.is_assigned"
                  :disabled="!canEdit"
                  :id="`doctor-procedure-assigned-${procedure.id}`"
                  :name="`procedures[${procedure.id}][is_assigned]`"
                  type="checkbox"
                  class="h-4 w-4 rounded border-border/70 bg-card"
                />
              </td>
              <td class="py-2 pr-2 text-text">{{ procedure.name }}</td>
              <td class="py-2 pr-2 text-text/70">{{ procedure.category || '—' }}</td>
              <td class="py-2 pr-2 text-text/80">{{ procedure.duration_minutes }} хв</td>
              <td class="py-2 pr-2">
                <div class="flex items-center gap-2">
                  <label :for="`doctor-procedure-duration-${procedure.id}`" class="sr-only">
                    Персональна тривалість (хв)
                  </label>
                  <input
                    v-model="procedure.custom_duration_minutes"
                    :disabled="!canEdit || !procedure.is_assigned"
                    :id="`doctor-procedure-duration-${procedure.id}`"
                    :name="`procedures[${procedure.id}][custom_duration_minutes]`"
                    type="number"
                    min="5"
                    max="480"
                    placeholder="За замовчуванням"
                    class="w-32 rounded-lg bg-bg border border-border/80 px-2 py-1 text-xs text-text disabled:opacity-60"
                  />
                  <span class="text-xs text-text/60">хв</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
