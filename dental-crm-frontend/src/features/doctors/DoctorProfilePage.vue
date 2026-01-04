<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UIButton, UIAvatar, UIBadge, UITabs } from '../../ui'
import { doctorsApi } from './api'
import type { Doctor, DoctorProcedure } from './types'
import clinicApi from '../../services/clinicApi'

const route = useRoute()
const router = useRouter()
const doctorId = computed(() => Number(route.params.id))

const loading = ref(true)
const error = ref<string | null>(null)
const doctor = ref<Doctor | null>(null)
const procedures = ref<DoctorProcedure[]>([])
const proceduresSaving = ref(false)
const proceduresMessage = ref<string | null>(null)
const notes = ref('')
const activeTab = ref('procedures')
const status = ref<'active' | 'inactive' | 'vacation'>('active')
const avatarUploading = ref(false)
const contactSaving = ref(false)
const contactMessage = ref<string | null>(null)
const isEditingContacts = ref(false)
const clinics = ref<{ id: number; name: string }[]>([])
const selectedClinicIds = ref<number[]>([])
const availableClinics = computed(() =>
  Array.isArray(clinics.value) ? clinics.value : []
)

const contactForm = ref({
  phone: '',
  email: '',
  room: '',
  admin_contact: '',
  address: '',
  city: '',
  state: '',
  zip: '',
  vacation_from: '',
  vacation_to: ''
})

const mockHistory = ref([
  { id: 1, date: '2024-11-05', patient: 'Іван Петренко', procedure: 'Пломбування', status: 'done' },
  { id: 2, date: '2024-11-06', patient: 'Марія Коваленко', procedure: 'Огляд', status: 'planned' }
])

const patients = computed(() => {
  const uniq = new Map<string, string>()
  mockHistory.value.forEach((item) => {
    if (!uniq.has(item.patient)) uniq.set(item.patient, item.patient)
  })
  return Array.from(uniq.values())
})

const statusVariant = computed(() => {
  if (doctor.value?.status === 'vacation') return 'warning'
  if (doctor.value?.is_active === false || doctor.value?.status === 'inactive') return 'neutral'
  return 'success'
})

const statusLabel = (value: string | undefined) => {
  if (value === 'vacation') return 'Відпустка'
  if (value === 'inactive' || value === 'false') return 'Неактивний'
  return 'Активний'
}

const loadDoctor = async () => {
  loading.value = true
  error.value = null
  try {
    const [{ data: doctorData }, { data: proceduresData }, { data: clinicsData }] = await Promise.all([
      doctorsApi.get(doctorId.value),
      doctorsApi.procedures(doctorId.value),
      clinicApi.list()
    ])
    clinics.value = Array.isArray(clinicsData) ? clinicsData : Array.isArray(clinicsData?.data) ? clinicsData.data : []
    doctor.value = doctorData
    status.value = (doctorData.status as any) || (doctorData.is_active === false ? 'inactive' : 'active')
    selectedClinicIds.value = (doctorData.clinics || [])
      .map((c: any) => Number(c.id))
      .filter((v) => Number.isFinite(v))
    if (!selectedClinicIds.value.length && doctorData.clinic?.id) {
      selectedClinicIds.value = [Number(doctorData.clinic.id)]
    }
    contactForm.value = {
      phone: doctorData.phone || '',
      email: doctorData.email || '',
      room: doctorData.room || '',
      admin_contact: doctorData.admin_contact || '',
      address: doctorData.address || '',
      city: doctorData.city || '',
      state: doctorData.state || '',
      zip: doctorData.zip || '',
      vacation_from: doctorData.vacation_from || '',
      vacation_to: doctorData.vacation_to || ''
    }
    procedures.value = Array.isArray(proceduresData) ? proceduresData : []
  } catch (e: any) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Не вдалося завантажити лікаря'
  } finally {
    loading.value = false
  }
}

const saveProcedures = async () => {
  proceduresSaving.value = true
  proceduresMessage.value = null
  try {
    const payload = {
      procedures: procedures.value.map((proc) => ({
        procedure_id: proc.id,
        is_assigned: !!proc.is_assigned,
        custom_duration_minutes:
          proc.custom_duration_minutes === null || proc.custom_duration_minutes === undefined
            ? null
            : Number(proc.custom_duration_minutes)
      }))
    }
    await doctorsApi.saveProcedures(doctorId.value, payload)
    proceduresMessage.value = 'Процедури оновлено'
    await loadDoctor()
  } catch (e: any) {
    console.error(e)
    proceduresMessage.value = e?.response?.data?.message || 'Помилка збереження процедур'
  } finally {
    proceduresSaving.value = false
  }
}

onMounted(loadDoctor)

const toggleAssignment = (proc: DoctorProcedure) => {
  proc.is_assigned = !proc.is_assigned
  if (!proc.is_assigned) {
    proc.custom_duration_minutes = null
  }
}

const onUploadPhoto = () => {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/*'
  input.onchange = async () => {
    const file = input.files?.[0]
    if (!file) return
    avatarUploading.value = true
    try {
      await doctorsApi.uploadAvatar(doctorId.value, file)
      await loadDoctor()
    } catch (e: any) {
      alert(e?.response?.data?.message || 'Не вдалося оновити фото')
    } finally {
      avatarUploading.value = false
    }
  }
  input.click()
}

const tabs = [
  { id: 'procedures', label: 'Процедури', badge: () => procedures.value.filter((p) => p.is_assigned).length },
  { id: 'appointments', label: 'Прийоми', badge: () => mockHistory.value.length },
  { id: 'patients', label: 'Пацієнти', badge: () => patients.value.length },
  { id: 'notes', label: 'Нотатки' },
  { id: 'activity', label: 'Активності' }
]

const goBack = () => {
  router.push({ name: 'doctors' })
}

const saveStatus = async () => {
  if (!doctor.value) return
  try {
    await doctorsApi.update(doctorId.value, {
      status: status.value,
      is_active: status.value === 'active'
    })
    await loadDoctor()
  } catch (e: any) {
    alert(e?.response?.data?.message || 'Не вдалося оновити статус')
  }
}

const saveContact = async () => {
  contactSaving.value = true
  contactMessage.value = null

  const phoneOk =
    !contactForm.value.phone ||
    /^(\+?\d[\d\s\-()]{6,20})$/.test(contactForm.value.phone.trim())

  const emailOk =
    !contactForm.value.email ||
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactForm.value.email.trim())

  if (contactForm.value.vacation_from && contactForm.value.vacation_to) {
    if (contactForm.value.vacation_to < contactForm.value.vacation_from) {
      contactMessage.value = 'Дата завершення відпустки раніше за початок'
      contactSaving.value = false
      return
    }
  }

  if ((contactForm.value.vacation_from && !contactForm.value.vacation_to) || (!contactForm.value.vacation_from && contactForm.value.vacation_to)) {
    contactMessage.value = 'Вкажіть обидві дати відпустки (з / до)'
    contactSaving.value = false
    return
  }

  if (!phoneOk) {
    contactMessage.value = 'Невірний формат телефону'
    contactSaving.value = false
    return
  }
  if (!emailOk) {
    contactMessage.value = 'Невірний email'
    contactSaving.value = false
    return
  }
  try {
    const payload = {
      ...contactForm.value,
      is_active: status.value === 'active',
      status: status.value,
      clinic_ids: selectedClinicIds.value
    }
    await doctorsApi.update(doctorId.value, payload)
    contactMessage.value = 'Дані оновлено'
    isEditingContacts.value = false
    await loadDoctor()
  } catch (e: any) {
    contactMessage.value = e?.response?.data?.message || 'Не вдалося оновити дані'
  } finally {
    contactSaving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
      <div>
        <button
          type="button"
          class="mb-2 inline-flex items-center gap-2 text-sm text-text/70 hover:text-text"
          @click="goBack"
        >
          ← До списку лікарів
        </button>
        <h1 class="text-2xl font-bold text-text">
          {{ doctor?.full_name || 'Лікар' }}
        </h1>
        <p class="text-sm text-text/70">Анкета лікаря, процедури та історія прийомів.</p>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
    <div v-else-if="error" class="text-sm text-rose-400">❌ {{ error }}</div>

    <div v-else class="grid gap-6 lg:grid-cols-[380px,1fr]">
      <section class="bg-card/70 border border-border rounded-xl shadow-sm shadow-black/10 dark:shadow-black/30 p-5 space-y-5">
        <div class="flex items-start gap-4">
          <UIAvatar :src="doctor?.avatar_url || ''" :fallback-text="doctor?.full_name?.[0] || '?'" :size="96" />
          <div class="flex-1 space-y-1">
            <p class="text-lg font-semibold text-text">{{ doctor?.full_name }}</p>
            <UIBadge :variant="statusVariant" small>
              {{
                doctor?.status === 'vacation'
                  ? 'Відпустка'
                  : doctor?.is_active === false || doctor?.status === 'inactive'
                    ? 'Неактивний'
                    : 'Активний'
              }}
            </UIBadge>
            <p class="text-sm text-text/70">{{ doctor?.specialization || 'Спеціалізація не вказана' }}</p>
          </div>
        </div>

          <div class="grid gap-3 text-sm text-text/80">
          <div class="rounded-lg border border-border/60 bg-bg/40 p-3 space-y-2">
            <div class="flex items-center justify-between gap-2">
              <div class="text-sm font-semibold text-text/90">Клініки</div>
              <UIButton size="sm" variant="secondary" @click="isEditingContacts = !isEditingContacts">
                {{ isEditingContacts ? 'Сховати' : 'Редагувати' }}
              </UIButton>
            </div>

            <div v-if="!isEditingContacts" class="text-text">
              {{
                doctor?.clinics?.length
                  ? doctor?.clinics?.map((c) => c.name).join(', ')
                  : doctor?.clinic?.name || 'Не привʼязано'
              }}
            </div>

            <div v-else class="space-y-2">
              <div class="flex flex-col gap-2 max-h-40 overflow-y-auto custom-scrollbar">
                <label
                  v-for="clinic in availableClinics"
                  :key="clinic.id"
                  class="flex items-center gap-2 text-sm text-text/90"
                >
                  <input
                    v-model="selectedClinicIds"
                    :value="clinic.id"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border/80 bg-bg"
                  />
                  <span>{{ clinic.name }}</span>
                </label>
              </div>
              <p v-if="!availableClinics.length" class="text-xs text-text/60">
                Немає доступних клінік. Додайте клініку або надайте доступ.
              </p>
            </div>
          </div>

            <div v-if="!isEditingContacts" class="space-y-2 rounded-lg border border-border/60 bg-bg/40 p-3">
              <div class="grid sm:grid-cols-2 gap-3">
                <div>
                  <div class="text-xs text-text/60 uppercase">Телефон</div>
                  <div class="text-text">{{ doctor?.phone || '—' }}</div>
                </div>
                <div>
                  <div class="text-xs text-text/60 uppercase">Email</div>
                  <div class="text-text break-all">{{ doctor?.email || '—' }}</div>
                </div>
              </div>
              <div class="grid sm:grid-cols-2 gap-3">
                <div>
                  <div class="text-xs text-text/60 uppercase">Кабінет</div>
                  <div class="text-text">{{ doctor?.room || '—' }}</div>
                </div>
                <div>
                  <div class="text-xs text-text/60 uppercase">Адміністратор</div>
                  <div class="text-text">{{ doctor?.admin_contact || '—' }}</div>
                </div>
              </div>
              <div>
                <div class="text-xs text-text/60 uppercase">Адреса</div>
                <div class="text-text">
                  {{ doctor?.address || '—' }}
                  <template v-if="doctor?.city">, {{ doctor?.city }}</template>
                  <template v-if="doctor?.state">, {{ doctor?.state }}</template>
                  <template v-if="doctor?.zip">, {{ doctor?.zip }}</template>
                </div>
              </div>
              <UIButton size="sm" variant="secondary" @click="isEditingContacts = true">Редагувати профіль</UIButton>
            </div>

            <div v-else class="grid gap-3">
              <div class="grid sm:grid-cols-2 gap-3">
                <label class="space-y-1 text-sm text-text/80">
                  <span class="text-xs text-text/60 uppercase">Телефон</span>
                  <input
                    v-model="contactForm.phone"
                    type="text"
                    class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm"
                    placeholder="+380..."
                  />
                </label>
                <label class="space-y-1 text-sm text-text/80">
                  <span class="text-xs text-text/60 uppercase">Email</span>
                  <input
                    v-model="contactForm.email"
                    type="email"
                    class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm"
                    placeholder="user@example.com"
                  />
                </label>
              </div>
              <div class="grid sm:grid-cols-2 gap-3">
                <label class="space-y-1 text-sm text-text/80">
                  <span class="text-xs text-text/60 uppercase">Кабінет</span>
                  <input v-model="contactForm.room" type="text" class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm" />
                </label>
              </div>
              <label class="space-y-1 text-sm text-text/80">
                <span class="text-xs text-text/60 uppercase">Адреса</span>
                <input v-model="contactForm.address" type="text" class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm" />
              </label>
              <div class="grid sm:grid-cols-3 gap-3">
                <label class="space-y-1 text-sm text-text/80">
                  <span class="text-xs text-text/60 uppercase">Місто</span>
                  <input v-model="contactForm.city" type="text" class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm" />
                </label>
                <label class="space-y-1 text-sm text-text/80">
                  <span class="text-xs text-text/60 uppercase">Область</span>
                  <input v-model="contactForm.state" type="text" class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm" />
                </label>
                <label class="space-y-1 text-sm text-text/80">
                  <span class="text-xs text-text/60 uppercase">ZIP</span>
                  <input v-model="contactForm.zip" type="text" class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm" />
                </label>
              </div>
              <div class="flex items-center gap-2">
                <UIButton size="sm" variant="ghost" @click="isEditingContacts = false">Скасувати</UIButton>
                <span v-if="contactMessage" class="text-xs text-emerald-400">{{ contactMessage }}</span>
              </div>
            </div>
          </div>

        <div class="flex flex-wrap items-center gap-3">
          <UIButton variant="primary" size="sm" :loading="avatarUploading" @click="onUploadPhoto">
            Змінити фото
          </UIButton>
          <div class="flex items-center gap-2">
            <label for="doctor-status" class="text-sm text-text/70">Статус</label>
            <select
              id="doctor-status"
              v-model="status"
              class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
            >
              <option value="active">Активний</option>
              <option value="inactive">Неактивний</option>
              <option value="vacation">Відпустка</option>
            </select>
          </div>

            <div v-if="status === 'vacation'" class="grid sm:grid-cols-2 gap-3">
              <label class="space-y-1 text-sm text-text/80">
                <span class="text-xs text-text/60 uppercase">Відпустка з</span>
                <input v-model="contactForm.vacation_from" type="date" class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm" />
              </label>
              <label class="space-y-1 text-sm text-text/80">
                <span class="text-xs text-text/60 uppercase">До</span>
                <input v-model="contactForm.vacation_to" type="date" class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm" />
              </label>
            </div>
        </div>

        <div class="flex justify-end gap-2">
          <UIButton size="sm" variant="secondary" :loading="contactSaving" @click="saveContact">Зберегти</UIButton>
          <span v-if="contactMessage" class="text-xs text-emerald-400 self-center">{{ contactMessage }}</span>
        </div>
      </section>

      <section class="bg-card/70 border border-border rounded-xl shadow-sm shadow-black/10 dark:shadow-black/30 p-5 space-y-5">
        <UITabs :tabs="tabs.map((t) => ({ ...t, badge: typeof t.badge === 'function' ? t.badge() : t.badge }))" v-model="activeTab" />

        <div v-if="activeTab === 'procedures'" class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-text/90">Процедури</p>
              <p class="text-xs text-text/70">Додайте або приберіть процедури лікаря.</p>
            </div>
            <UIButton size="sm" @click="saveProcedures" :loading="proceduresSaving">Зберегти</UIButton>
          </div>

          <div v-if="proceduresMessage" class="text-sm" :class="proceduresMessage.includes('Помилка') ? 'text-rose-400' : 'text-emerald-400'">
            {{ proceduresMessage }}
          </div>

          <div v-if="!procedures.length" class="text-sm text-text/70">Немає процедур для цієї клініки.</div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-xs uppercase text-text/60 border-b border-border">
                <tr>
                  <th class="px-3 py-2 text-left">Активна</th>
                  <th class="px-3 py-2 text-left">Процедура</th>
                  <th class="px-3 py-2 text-left">Категорія</th>
                  <th class="px-3 py-2 text-left">Базова тривалість</th>
                  <th class="px-3 py-2 text-left">Персональна тривалість</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="proc in procedures" :key="proc.id" class="border-b border-border/60">
                  <td class="px-3 py-2">
                    <input type="checkbox" class="h-4 w-4 rounded border-border/70 bg-card" :checked="proc.is_assigned" @change="toggleAssignment(proc)" />
                  </td>
                  <td class="px-3 py-2 text-text">{{ proc.name }}</td>
                  <td class="px-3 py-2 text-text/70">{{ proc.category || '—' }}</td>
                  <td class="px-3 py-2 text-text/80">{{ proc.duration_minutes || '—' }} хв</td>
                  <td class="px-3 py-2">
                    <input
                      v-model.number="proc.custom_duration_minutes"
                      :disabled="!proc.is_assigned"
                      type="number"
                      min="5"
                      max="480"
                      placeholder="За замовчуванням"
                      class="w-32 rounded-lg bg-bg border border-border/80 px-2 py-1 text-xs text-text disabled:opacity-60"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else-if="activeTab === 'appointments'" class="space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-text/90">Історія прийомів</p>
            <UIButton size="sm" variant="ghost" @click="mockHistory = [...mockHistory]">Оновити</UIButton>
          </div>
          <div v-if="!mockHistory.length" class="text-sm text-text/70">Ще немає прийомів.</div>
          <div v-else class="space-y-2">
            <div v-for="appt in mockHistory" :key="appt.id" class="rounded-lg border border-border bg-bg/60 px-3 py-2">
              <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-text">{{ appt.patient }}</p>
                <UIBadge :variant="appt.status === 'done' ? 'success' : 'info'" small>
                  {{ appt.status === 'done' ? 'Виконано' : 'Заплановано' }}
                </UIBadge>
              </div>
              <p class="text-xs text-text/70">{{ appt.date }} • {{ appt.procedure }}</p>
            </div>
          </div>
        </div>

        <div v-else-if="activeTab === 'patients'" class="space-y-2">
          <p class="text-sm font-semibold text-text/90">Пацієнти</p>
          <div v-if="!patients.length" class="text-sm text-text/70">Немає даних.</div>
          <ul v-else class="space-y-1">
            <li v-for="patient in patients" :key="patient" class="flex items-center justify-between rounded-lg border border-border bg-bg/60 px-3 py-2">
              <span class="text-sm text-text">{{ patient }}</span>
              <UIButton size="sm" variant="ghost">Відкрити</UIButton>
            </li>
          </ul>
        </div>

        <div v-else-if="activeTab === 'notes'" class="space-y-3">
          <p class="text-sm font-semibold text-text/90">Нотатки (admin)</p>
          <textarea
            v-model="notes"
            rows="6"
            placeholder="Внутрішні нотатки..."
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
          <div class="flex justify-end">
            <UIButton size="sm" variant="secondary">Зберегти нотатки</UIButton>
          </div>
        </div>

        <div v-else class="space-y-2">
          <p class="text-sm font-semibold text-text/90">Активності</p>
          <div class="space-y-2 text-sm text-text/80">
            <div class="rounded-lg border border-border bg-bg/60 px-3 py-2">
              05.11 — Нагадування: перевірити план лікування.
            </div>
            <div class="rounded-lg border border-border bg-bg/60 px-3 py-2">
              06.11 — Повідомлення пацієнту про перенос.
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

