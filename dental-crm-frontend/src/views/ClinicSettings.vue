<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import clinicApi from '../services/clinicApi'
import apiClient from '../services/apiClient'
import roomApi from '../services/roomApi'
import { useAuth } from '../composables/useAuth'
import { useToast } from '../composables/useToast'
import { UIButton, UISelect, UIAccordion } from '../ui'
import LogoUploader from '../components/clinic/LogoUploader.vue'
import type { Clinic } from '../types/api'

const { user } = useAuth()
const { showToast } = useToast()

const clinics = ref<any[]>([])
const selectedClinicId = ref<number | string>('')
const currentClinic = ref<Clinic | null>(null)

const rooms = ref([])
const workingHours = ref([])

const loadingRooms = ref(false)
const loadingHours = ref(false)
const savingHours = ref(false)
const savingClinic = ref(false)
const loadingClinic = ref(false)
const errorRooms = ref(null)
const errorHours = ref(null)

// Clinic form data
const formData = ref({
  name: '',
  slogan: '',
  currency_code: 'UAH',
  phone_main: '',
  email_public: '',
  website: '',
  city: '',
  address_street: '',
  address_building: '',
  postal_code: '',
  requisites: {
    legal_name: '',
    tax_id: '',
    iban: '',
    bank_name: '',
    mfo: ''
  }
})

// Original data for tracking unsaved changes
const originalData = ref({ ...formData.value })

const roomForm = ref({
  name: '',
  equipment: '',
  notes: '',
  is_active: true
})

const weekdays = [
  { id: 1, label: 'Пн' },
  { id: 2, label: 'Вт' },
  { id: 3, label: 'Ср' },
  { id: 4, label: 'Чт' },
  { id: 5, label: 'Пт' },
  { id: 6, label: 'Сб' },
  { id: 7, label: 'Нд' }
]

const currencyOptions = [
  { value: 'UAH', label: 'UAH (₴)' },
  { value: 'USD', label: 'USD ($)' },
  { value: 'EUR', label: 'EUR (€)' }
]

const normalizeTime = (value) => (value ? value.slice(0, 5) : '')

const loadClinics = async () => {
  if (user.value?.global_role === 'super_admin') {
    const { data } = await clinicApi.list()
    clinics.value = data.data ?? data
  } else {
    const { data } = await clinicApi.listMine()
    clinics.value = (data.clinics ?? []).map((clinic) => ({
      id: clinic.clinic_id,
      name: clinic.clinic_name
    }))
  }

  if (!selectedClinicId.value && clinics.value.length) {
    selectedClinicId.value = clinics.value[0].id
  }
}

const loadClinic = async () => {
  if (!selectedClinicId.value) return
  loadingClinic.value = true
  try {
    const { data } = await clinicApi.get(selectedClinicId.value)
    currentClinic.value = data
    formData.value = {
      name: data.name || '',
      slogan: data.slogan || '',
      currency_code: data.currency_code || 'UAH',
      phone_main: data.phone_main || '',
      email_public: data.email_public || '',
      website: data.website || '',
      city: data.city || '',
      address_street: data.address_street || '',
      address_building: data.address_building || '',
      postal_code: data.postal_code || '',
      requisites: {
        legal_name: data.requisites?.legal_name || '',
        tax_id: data.requisites?.tax_id || '',
        iban: data.requisites?.iban || '',
        bank_name: data.requisites?.bank_name || '',
        mfo: data.requisites?.mfo || ''
      }
    }
    originalData.value = JSON.parse(JSON.stringify(formData.value))
  } catch (err: any) {
    console.error(err)
    showToast('Не вдалося завантажити дані клініки', 'error')
  } finally {
    loadingClinic.value = false
  }
}

const loadRooms = async () => {
  if (!selectedClinicId.value) return
  loadingRooms.value = true
  errorRooms.value = null
  try {
    const { data } = await roomApi.list({ clinic_id: selectedClinicId.value })
    rooms.value = data.data ?? data
  } catch (err) {
    console.error(err)
    errorRooms.value = 'Не вдалося завантажити кабінети'
  } finally {
    loadingRooms.value = false
  }
}

const loadWorkingHours = async () => {
  if (!selectedClinicId.value) return
  loadingHours.value = true
  errorHours.value = null
  try {
    const { data } = await apiClient.get(`/clinics/${selectedClinicId.value}/working-hours`)
    workingHours.value = (data ?? [])
      .map((day) => ({
        ...day,
        start_time: normalizeTime(day.start_time),
        end_time: normalizeTime(day.end_time),
        break_start: normalizeTime(day.break_start),
        break_end: normalizeTime(day.break_end)
      }))
      .sort((a, b) => a.weekday - b.weekday)
  } catch (err) {
    console.error(err)
    errorHours.value = 'Не вдалося завантажити графік роботи'
  } finally {
    loadingHours.value = false
  }
}

const validateIBAN = (iban: string): boolean => {
  if (!iban) return true // Empty is valid (optional field)
  return /^UA\d{27}$/.test(iban)
}

const validateEmail = (email: string): boolean => {
  if (!email) return true // Empty is valid (optional field)
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

const saveClinicData = async (section?: string) => {
  if (!selectedClinicId.value) return

  // Validate IBAN
  if (formData.value.requisites.iban && !validateIBAN(formData.value.requisites.iban)) {
    showToast('Невірний формат IBAN. Очікується формат: UA + 27 цифр', 'error')
    return
  }

  // Validate email
  if (formData.value.email_public && !validateEmail(formData.value.email_public)) {
    showToast('Невірний формат email', 'error')
    return
  }

  savingClinic.value = true
  try {
    const payload: any = {}
    
    if (!section || section === 'general') {
      payload.name = formData.value.name
      payload.slogan = formData.value.slogan
      payload.currency_code = formData.value.currency_code
    }
    
    if (!section || section === 'contacts') {
      payload.phone_main = formData.value.phone_main || null
      payload.email_public = formData.value.email_public || null
      payload.website = formData.value.website || null
      payload.city = formData.value.city || null
      payload.address_street = formData.value.address_street || null
      payload.address_building = formData.value.address_building || null
      payload.postal_code = formData.value.postal_code || null
    }
    
    if (!section || section === 'requisites') {
      payload.requisites = {
        legal_name: formData.value.requisites.legal_name || null,
        tax_id: formData.value.requisites.tax_id || null,
        iban: formData.value.requisites.iban || null,
        bank_name: formData.value.requisites.bank_name || null,
        mfo: formData.value.requisites.mfo || null
      }
    }

    await clinicApi.update(selectedClinicId.value, payload)
    await loadClinic() // Reload to update originalData
    showToast('Дані збережено успішно', 'success')
  } catch (err: any) {
    console.error(err)
    showToast(err.response?.data?.message || 'Не вдалося зберегти дані', 'error')
  } finally {
    savingClinic.value = false
  }
}

// Track unsaved changes
const hasUnsavedChanges = (section: 'general' | 'contacts' | 'requisites') => {
  const sectionKeys: Record<string, string[]> = {
    general: ['name', 'slogan', 'currency_code'],
    contacts: ['phone_main', 'email_public', 'website', 'city', 'address_street', 'address_building', 'postal_code'],
    requisites: ['requisites']
  }

  const keys = sectionKeys[section]
  if (section === 'requisites') {
    return JSON.stringify(formData.value.requisites) !== JSON.stringify(originalData.value.requisites)
  }

  return keys.some(key => {
    if (key.includes('.')) {
      const [parent, child] = key.split('.')
      return formData.value[parent]?.[child] !== originalData.value[parent]?.[child]
    }
    return formData.value[key] !== originalData.value[key]
  })
}

const resetRoomForm = () => {
  roomForm.value = {
    name: '',
    equipment: '',
    notes: '',
    is_active: true
  }
}

const createRoom = async () => {
  if (!roomForm.value.name) return
  try {
    await apiClient.post('/rooms', {
      clinic_id: selectedClinicId.value,
      ...roomForm.value
    })
    resetRoomForm()
    await loadRooms()
  } catch (err) {
    console.error(err)
    errorRooms.value = err.response?.data?.message || 'Не вдалося створити кабінет'
  }
}

const toggleRoomActive = async (room) => {
  try {
    await apiClient.put(`/rooms/${room.id}`, {
      is_active: room.is_active
    })
  } catch (err) {
    console.error(err)
    errorRooms.value = 'Не вдалося оновити статус кабінету'
  }
}

const deleteRoom = async (room) => {
  if (!window.confirm(`Видалити кабінет "${room.name}"?`)) return
  try {
    await apiClient.delete(`/rooms/${room.id}`)
    await loadRooms()
  } catch (err) {
    console.error(err)
    errorRooms.value = 'Не вдалося видалити кабінет'
  }
}

const saveWorkingHours = async () => {
  if (!selectedClinicId.value) return
  savingHours.value = true
  errorHours.value = null
  try {
    const payload = workingHours.value.map((day) => ({
      weekday: day.weekday,
      is_working: !!day.is_working,
      start_time: day.is_working ? day.start_time || null : null,
      end_time: day.is_working ? day.end_time || null : null,
      break_start: day.is_working ? day.break_start || null : null,
      break_end: day.is_working ? day.break_end || null : null
    }))
    await apiClient.put(`/clinics/${selectedClinicId.value}/working-hours`, { days: payload })
    await loadWorkingHours()
    showToast('Графік роботи збережено', 'success')
  } catch (err) {
    console.error(err)
    errorHours.value = err.response?.data?.message || 'Не вдалося зберегти графік'
  } finally {
    savingHours.value = false
  }
}

const handleLogoUploaded = (url: string) => {
  if (currentClinic.value) {
    currentClinic.value.logo_url = url
  }
}

watch(selectedClinicId, async () => {
  if (selectedClinicId.value) {
    await Promise.all([loadClinic(), loadRooms(), loadWorkingHours()])
  }
})

onMounted(async () => {
  await loadClinics()
  if (selectedClinicId.value) {
    await Promise.all([loadClinic(), loadRooms(), loadWorkingHours()])
  }
})
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold">Налаштування клініки</h1>
      <p class="text-sm text-text/70">Управління налаштуваннями, кабінетами та графіком роботи клініки.</p>
    </header>

    <section class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <label
        for="clinic-settings-clinic"
        class="block text-xs uppercase tracking-wide text-text/70 mb-2"
      >
        Клініка
      </label>
      <select
        v-model="selectedClinicId"
        id="clinic-settings-clinic"
        name="clinic_id"
        class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm w-full md:w-72"
      >
        <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
          {{ clinic.name }}
        </option>
      </select>
    </section>

    <div v-if="!selectedClinicId" class="text-center py-12 text-text/70">
      Оберіть клініку для налаштування
    </div>

    <div v-else-if="loadingClinic" class="text-center py-12 text-text/70">
      Завантаження даних клініки...
    </div>

    <div v-else class="space-y-4">
      <!-- Accordion 1: Основна інформація та Брендинг -->
      <UIAccordion
        title="Основна інформація та Брендинг"
        :has-unsaved-changes="hasUnsavedChanges('general')"
      >
        <div class="space-y-4">
          <LogoUploader
            :clinic-id="selectedClinicId"
            :current-logo-url="currentClinic?.logo_url"
            @uploaded="handleLogoUploaded"
          />

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">
              Назва клініки <span class="text-red-400">*</span>
            </label>
            <input
              v-model="formData.name"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="Назва клініки"
              required
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Слоган</label>
            <input
              v-model="formData.slogan"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="Короткий слоган клініки"
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Валюта за замовчуванням</label>
            <UISelect
              v-model="formData.currency_code"
              :options="currencyOptions"
              placeholder="Оберіть валюту"
            />
          </div>

          <div class="flex justify-end">
            <UIButton
              variant="primary"
              size="sm"
              :loading="savingClinic"
              @click="saveClinicData('general')"
            >
              Зберегти
            </UIButton>
          </div>
        </div>
      </UIAccordion>

      <!-- Accordion 2: Контакти та Адреса -->
      <UIAccordion
        title="Контакти та Адреса"
        :has-unsaved-changes="hasUnsavedChanges('contacts')"
      >
        <div class="space-y-4">
          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Телефон рецепції</label>
            <input
              v-model="formData.phone_main"
              type="tel"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="+380 XX XXX XX XX"
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Email для пацієнтів</label>
            <input
              v-model="formData.email_public"
              type="email"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="info@clinic.com"
            />
            <p v-if="formData.email_public && !validateEmail(formData.email_public)" class="mt-1 text-xs text-red-400">
              Невірний формат email
            </p>
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Веб-сайт</label>
            <input
              v-model="formData.website"
              type="url"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="https://clinic.com"
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Місто</label>
            <input
              v-model="formData.city"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="Київ"
            />
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs uppercase text-text/70 mb-1">Вулиця</label>
              <input
                v-model="formData.address_street"
                type="text"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                placeholder="вул. Хрещатик"
              />
            </div>
            <div>
              <label class="block text-xs uppercase text-text/70 mb-1">Будинок</label>
              <input
                v-model="formData.address_building"
                type="text"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                placeholder="10"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Поштовий індекс</label>
            <input
              v-model="formData.postal_code"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="01001"
            />
          </div>

          <div class="flex justify-end">
            <UIButton
              variant="primary"
              size="sm"
              :loading="savingClinic"
              @click="saveClinicData('contacts')"
            >
              Зберегти
            </UIButton>
          </div>
        </div>
      </UIAccordion>

      <!-- Accordion 3: Фінансові Реквізити -->
      <UIAccordion
        title="Фінансові Реквізити"
        :has-unsaved-changes="hasUnsavedChanges('requisites')"
      >
        <div class="space-y-4">
          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Юридична назва / ФОП</label>
            <input
              v-model="formData.requisites.legal_name"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder='ТОВ "Зуб і Ко" або ФОП Іванов'
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">ЄДРПОУ / ІПН</label>
            <input
              v-model="formData.requisites.tax_id"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="12345678"
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">IBAN</label>
            <input
              v-model="formData.requisites.iban"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="UA123456789012345678901234567"
              maxlength="29"
            />
            <p v-if="formData.requisites.iban && !validateIBAN(formData.requisites.iban)" class="mt-1 text-xs text-red-400">
              Невірний формат IBAN. Очікується: UA + 27 цифр
            </p>
            <p v-else-if="formData.requisites.iban" class="mt-1 text-xs text-text/60">
              Формат: UA + 27 цифр
            </p>
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Назва банку</label>
            <input
              v-model="formData.requisites.bank_name"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="ПриватБанк"
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">МФО (опційно)</label>
            <input
              v-model="formData.requisites.mfo"
              type="text"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              placeholder="305299"
            />
          </div>

          <div class="flex justify-end">
            <UIButton
              variant="primary"
              size="sm"
              :loading="savingClinic"
              @click="saveClinicData('requisites')"
            >
              Зберегти
            </UIButton>
          </div>
        </div>
      </UIAccordion>

      <!-- Accordion 4: Кабінети -->
      <UIAccordion title="Кабінети">
        <div class="space-y-4">
          <div v-if="loadingRooms" class="text-sm text-text/70">Завантаження...</div>
          <div v-else-if="errorRooms" class="text-sm text-red-400">{{ errorRooms }}</div>

          <div v-else class="space-y-3">
            <div class="space-y-2">
              <div class="grid md:grid-cols-2 gap-3">
                <input
                  v-model="roomForm.name"
                  type="text"
                  placeholder="Назва кабінету"
                  class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm"
                />
                <input
                  v-model="roomForm.equipment"
                  type="text"
                  placeholder="Обладнання (опційно)"
                  class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm"
                />
              </div>
              <textarea
                v-model="roomForm.notes"
                rows="2"
                placeholder="Нотатки"
                class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm w-full"
              />
              <label class="flex items-center gap-2 text-sm text-text/80">
                <input
                  v-model="roomForm.is_active"
                  type="checkbox"
                  class="accent-emerald-500"
                />
                Активний кабінет
              </label>
              <UIButton variant="secondary" size="sm" @click="createRoom">
                Додати кабінет
              </UIButton>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="text-text/70 text-xs uppercase">
                  <tr>
                    <th class="text-left py-2 px-3">Кабінет</th>
                    <th class="text-left py-2 px-3">Обладнання</th>
                    <th class="text-left py-2 px-3">Статус</th>
                    <th class="text-left py-2 px-3"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="room in rooms" :key="room.id" class="border-t border-border">
                    <td class="py-2 px-3 text-text/90">{{ room.name }}</td>
                    <td class="py-2 px-3 text-text/70">{{ room.equipment || '—' }}</td>
                    <td class="py-2 px-3">
                      <label class="inline-flex items-center gap-2 text-xs text-text/80">
                        <input
                          v-model="room.is_active"
                          type="checkbox"
                          class="accent-emerald-500"
                          @change="toggleRoomActive(room)"
                        />
                        {{ room.is_active ? 'Активний' : 'Неактивний' }}
                      </label>
                    </td>
                    <td class="py-2 px-3 text-right">
                      <button
                        class="text-xs text-red-400 hover:text-red-300"
                        @click="deleteRoom(room)"
                      >
                        Видалити
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </UIAccordion>

      <!-- Accordion 5: Графік роботи -->
      <UIAccordion title="Графік роботи">
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-sm text-text/70">Налаштуйте робочі години для клініки</p>
            <UIButton
              variant="primary"
              size="sm"
              :loading="savingHours"
              :disabled="loadingHours"
              @click="saveWorkingHours"
            >
              {{ savingHours ? 'Збереження...' : 'Зберегти графік' }}
            </UIButton>
          </div>

          <div v-if="loadingHours" class="text-sm text-text/70">Завантаження...</div>
          <div v-else-if="errorHours" class="text-sm text-red-400">{{ errorHours }}</div>

          <div v-else class="space-y-3">
            <div
              v-for="day in workingHours"
              :key="day.weekday"
              class="grid grid-cols-12 items-center gap-2 rounded-lg border border-border bg-bg/60 px-3 py-2 text-xs"
            >
              <div class="col-span-2 font-semibold text-text/90">
                {{ weekdays.find((w) => w.id === day.weekday)?.label }}
              </div>
              <div class="col-span-2">
                <label class="flex items-center gap-2 text-text/80">
                  <input
                    v-model="day.is_working"
                    type="checkbox"
                    class="accent-emerald-500"
                  />
                  Працює
                </label>
              </div>
              <div class="col-span-4 flex items-center gap-2">
                <input
                  v-model="day.start_time"
                  type="time"
                  class="rounded bg-card border border-border/80 px-2 py-1"
                  :disabled="!day.is_working"
                />
                <span class="text-text/60">—</span>
                <input
                  v-model="day.end_time"
                  type="time"
                  class="rounded bg-card border border-border/80 px-2 py-1"
                  :disabled="!day.is_working"
                />
              </div>
              <div class="col-span-4 flex items-center gap-2">
                <input
                  v-model="day.break_start"
                  type="time"
                  class="rounded bg-card border border-border/80 px-2 py-1"
                  :disabled="!day.is_working"
                />
                <span class="text-text/60">—</span>
                <input
                  v-model="day.break_end"
                  type="time"
                  class="rounded bg-card border border-border/80 px-2 py-1"
                  :disabled="!day.is_working"
                />
              </div>
            </div>
            <p class="text-[11px] text-text/60">
              Вкажіть час початку/закінчення та перерви для кожного робочого дня.
            </p>
          </div>
        </div>
      </UIAccordion>
    </div>
  </div>
</template>
