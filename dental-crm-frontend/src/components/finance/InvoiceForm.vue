<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useFinanceStore } from '../../stores/useFinanceStore'
import { UIButton, UIBadge, UISelect } from '../../ui'
import { useToast } from '../../composables/useToast'
import { useAuth } from '../../composables/useAuth'
import invoiceApi from '../../services/invoiceApi'
import clinicApi from '../../services/clinicApi'
import patientApi from '../../services/patientApi'

interface InvoiceItem {
  id?: number
  procedure_id?: number | null
  name: string
  quantity: number
  price: number
  total: number
}

const props = withDefaults(defineProps<{
  modelValue?: boolean
  patientId?: number
  appointmentId?: number | null
  invoiceId?: number | null
  inline?: boolean // Без власної модалки, для використання в drawer
}>(), {
  modelValue: true,
  patientId: undefined,
  appointmentId: null,
  invoiceId: null,
  inline: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'saved', invoice: any): void
  (e: 'created', invoice: any): void
  (e: 'cancel'): void
}>()

const { user } = useAuth()
const { showToast } = useToast()
const financeStore = useFinanceStore()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const items = ref<InvoiceItem[]>([])
const discount = ref<number>(0)
const discountType = ref<'percent' | 'amount'>('percent')
const searchQuery = ref('')
const showProcedureSearch = ref(false)
const description = ref('')
const dueDate = ref<string | null>(null)

// Клініка
const selectedClinicId = ref<number | null>(null)
const clinics = ref<any[]>([])
const loadingClinics = ref(false)

// Перевірка чи супер-адмін
const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin')

// Автоматичне визначення клініки для не-супер-адмінів
const resolvedClinicId = computed(() => {
  if (isSuperAdmin.value) {
    return selectedClinicId.value
  }
  return user.value?.doctor?.clinic_id || user.value?.clinics?.[0]?.id || null
})

// Опції для вибору клініки
const clinicOptions = computed(() => {
  return clinics.value.map(c => ({
    value: c.id,
    label: c.name
  }))
})

// Завантажити клініки для супер-адміна
const loadClinics = async () => {
  if (!isSuperAdmin.value) return
  
  loadingClinics.value = true
  try {
    const { data } = await clinicApi.list()
    clinics.value = Array.isArray(data) ? data : (data?.data || [])
    // Автовибір першої клініки
    if (clinics.value.length > 0 && !selectedClinicId.value) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (err) {
    console.error('Failed to load clinics:', err)
  } finally {
    loadingClinics.value = false
  }
}

// Пацієнти
const selectedPatientId = ref<number | null>(null)
const patients = ref<any[]>([])
const loadingPatients = ref(false)
const patientSearchQuery = ref('')
const patientName = ref<string>('') // Ім'я пацієнта для режиму редагування

// Опції для вибору пацієнта
const patientOptions = computed(() => {
  return patients.value.map(p => ({
    value: p.id,
    label: `${p.full_name || ''}`.trim() + (p.phone ? ` (${p.phone})` : '')
  }))
})

// Пошук пацієнтів
const searchPatients = async (query?: string) => {
  loadingPatients.value = true
  try {
    const params: any = { per_page: 50 }
    if (query) params.search = query
    if (resolvedClinicId.value) params.clinic_id = resolvedClinicId.value
    
    const { data } = await patientApi.list(params)
    patients.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to load patients:', err)
  } finally {
    loadingPatients.value = false
  }
}

// Resolved patientId
const resolvedPatientId = computed(() => {
  return props.patientId || selectedPatientId.value
})

const isLocked = computed(() => {
  if (!props.invoiceId || !financeStore.currentInvoice) return false
  return (financeStore.currentInvoice.paid_amount || 0) > 0
})

const filteredProcedures = computed(() => {
  if (!searchQuery.value) return []
  const query = searchQuery.value.toLowerCase()
  return financeStore.procedures
    .filter((p) => {
      const nameMatch = p.name?.toLowerCase().includes(query)
      const codeMatch = p.code?.toLowerCase().includes(query)
      return nameMatch || codeMatch
    })
    .slice(0, 10)
})

const totalAmount = computed(() => {
  const subtotal = items.value.reduce((sum, item) => sum + item.total, 0)
  if (discountType.value === 'percent') {
    return subtotal * (1 - discount.value / 100)
  }
  return Math.max(0, subtotal - discount.value)
})

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

const addItem = (procedure?: any) => {
  const newItem: InvoiceItem = {
    procedure_id: procedure?.id || null,
    name: procedure?.name || '',
    quantity: 1,
    price: procedure?.price || 0,
    total: procedure?.price || 0
  }
  items.value.push(newItem)
  searchQuery.value = ''
  showProcedureSearch.value = false
}

const removeItem = (index: number) => {
  if (isLocked.value) {
    showToast('Редагування заблоковано через наявність оплат', 'error')
    return
  }
  items.value.splice(index, 1)
}

const updateItem = (index: number, field: keyof InvoiceItem, value: any) => {
  if (isLocked.value && (field === 'quantity' || field === 'price')) {
    showToast('Редагування заблоковано через наявність оплат', 'error')
    return
  }
  const item = items.value[index]
  item[field] = value
  if (field === 'quantity' || field === 'price') {
    item.total = item.quantity * item.price
  }
}

const loadInvoice = async () => {
  if (!props.invoiceId) return
  await financeStore.fetchInvoice(props.invoiceId)
  
  if (financeStore.currentInvoice) {
    const inv = financeStore.currentInvoice
    // Конвертувати price/total/quantity в числа (з бекенду приходять як strings)
    items.value = (inv.items || []).map((item: any) => ({
      ...item,
      quantity: Number(item.quantity) || 1,
      price: Number(item.price) || 0,
      total: Number(item.total) || 0,
    }))
    description.value = inv.description || ''
    dueDate.value = inv.due_date || null
    discount.value = Number(inv.discount_amount) || 0
    discountType.value = inv.discount_type || 'percentage'
    
    // Встановити пацієнта для редагування
    if (inv.patient_id) {
      selectedPatientId.value = inv.patient_id
      patientName.value = inv.patient?.full_name || ''
    }
    
    // Встановити клініку для редагування (супер-адмін)
    if (isSuperAdmin.value && inv.clinic_id) {
      selectedClinicId.value = inv.clinic_id
    }
  }
}

const save = async () => {
  if (items.value.length === 0) {
    showToast('Додайте хоча б одну послугу', 'error')
    return
  }

  if (totalAmount.value <= 0) {
    showToast('Сума рахунку повинна бути більше 0', 'error')
    return
  }

  const clinicId = resolvedClinicId.value
  if (!clinicId) {
    showToast('Оберіть клініку', 'error')
    return
  }

  try {

    if (props.invoiceId) {
      // Update existing invoice - replace items
      await invoiceApi.replaceItems(props.invoiceId, items.value)
      
      // Update description and due_date
      await invoiceApi.update(props.invoiceId, {
        description: description.value || null,
        due_date: dueDate.value || null
      })
      
      // Apply discount if any
      if (discount.value > 0) {
        await invoiceApi.applyDiscount(
          props.invoiceId,
          discountType.value === 'percent' ? 'percent' : 'fixed',
          discount.value
        )
      }
      
      // Refresh invoice data
      await financeStore.fetchInvoice(props.invoiceId)
      emit('saved', financeStore.currentInvoice)
    } else {
      // Create new invoice
      const patientId = resolvedPatientId.value
      if (!patientId) {
        showToast('Не обрано пацієнта', 'error')
        return
      }

      const invoice = await financeStore.createInvoice({
        clinic_id: clinicId,
        patient_id: patientId,
        appointment_id: props.appointmentId || null,
        items: items.value,
        description: description.value || undefined,
        due_date: dueDate.value || undefined
      })
      emit('saved', invoice)
      emit('created', invoice)
    }

    open.value = false
    emit('cancel')
  } catch (err: any) {
    // Error already handled in store
  }
}

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      await financeStore.fetchProcedures()
      if (props.invoiceId) {
        await loadInvoice()
      } else {
        items.value = []
        discount.value = 0
        description.value = ''
        dueDate.value = null
      }
    }
  }
)

onMounted(async () => {
  // Завантажити клініки для супер-адміна
  if (isSuperAdmin.value) {
    await loadClinics()
  }
  
  // Завантажити пацієнтів якщо не передано patientId
  if (!props.patientId) {
    await searchPatients()
  }
  
  // Для inline режиму - завжди ініціалізувати
  if (props.inline) {
    await financeStore.fetchProcedures()
    
    // Якщо редагування - завантажити дані рахунку
    if (props.invoiceId) {
      await loadInvoice()
    } else {
      // Нове створення - очистити форму
      items.value = []
      discount.value = 0
      description.value = ''
      dueDate.value = null
      selectedPatientId.value = null
    }
  } else if (props.modelValue) {
    await financeStore.fetchProcedures()
  }
})
</script>

<template>
  <!-- Inline mode: just the form content for use in drawer -->
  <div v-if="inline" class="space-y-6">
    <!-- Вибір клініки для супер-адміна -->
    <div v-if="isSuperAdmin" class="space-y-2">
      <label class="block text-xs uppercase text-text/70">Клініка <span class="text-red-400">*</span></label>
      <UISelect
        v-model="selectedClinicId"
        :options="clinicOptions"
        placeholder="Оберіть клініку"
        :disabled="loadingClinics"
      />
    </div>

    <!-- Вибір пацієнта (якщо не передано через props) -->
    <!-- Пацієнт: read-only при редагуванні, select при створенні -->
    <div v-if="invoiceId && patientName" class="space-y-2">
      <label class="block text-xs uppercase text-text/70">Пацієнт</label>
      <div class="px-3 py-2 bg-card/50 border border-border rounded-md text-text">
        {{ patientName }}
      </div>
    </div>
    <div v-else-if="!patientId" class="space-y-2">
      <label class="block text-xs uppercase text-text/70">Пацієнт <span class="text-red-400">*</span></label>
      <UISelect
        v-model="selectedPatientId"
        :options="patientOptions"
        placeholder="Оберіть пацієнта"
        :disabled="loadingPatients"
        searchable
      />
    </div>

    <div v-if="isLocked" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3">
      <p class="text-sm text-yellow-300">
        ⚠️ Редагування заблоковано через наявність оплат. Зробіть повернення коштів для
        розблокування.
      </p>
    </div>

    <!-- Items Table -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-text/90">Послуги</h3>
        <UIButton
          v-if="!isLocked"
          variant="secondary"
          size="sm"
          @click="showProcedureSearch = !showProcedureSearch"
        >
          + Додати послугу
        </UIButton>
      </div>

      <!-- Procedure Search -->
      <div v-if="showProcedureSearch && !isLocked" class="relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Пошук по прайсу (назва або код)..."
          class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          @focus="showProcedureSearch = true"
        />
        <div
          v-if="searchQuery && filteredProcedures.length > 0"
          class="absolute z-10 w-full mt-1 bg-card border border-border rounded-lg shadow-lg max-h-60 overflow-y-auto"
        >
          <button
            v-for="proc in filteredProcedures"
            :key="proc.id"
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-card/80 text-sm text-text/80 hover:text-text border-b border-border/60 last:border-0"
            @click="addItem(proc)"
          >
            <div class="font-medium">{{ proc.name }}</div>
            <div class="text-xs text-text/60">
              {{ proc.code || '—' }} • {{ formatMoney(proc.price || 0) }} грн
            </div>
          </button>
        </div>
      </div>

      <!-- Items Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
              <th class="px-4 py-2">Назва</th>
              <th class="px-4 py-2 w-24">Кількість</th>
              <th class="px-4 py-2 w-32">Ціна</th>
              <th class="px-4 py-2 w-32">Сума</th>
              <th v-if="!isLocked" class="px-4 py-2 w-16"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, index) in items"
              :key="index"
              class="border-b border-border/60 hover:bg-card/40"
            >
              <td class="px-4 py-2">
                <input
                  v-model="item.name"
                  type="text"
                  :readonly="isLocked"
                  class="w-full bg-transparent border-0 focus:outline-none text-text"
                  @input="updateItem(index, 'name', ($event.target as HTMLInputElement).value)"
                />
              </td>
              <td class="px-4 py-2">
                <input
                  v-model.number="item.quantity"
                  type="number"
                  min="1"
                  :readonly="isLocked"
                  class="w-full rounded bg-bg/50 border border-border/60 px-2 py-1 text-sm text-text"
                  @input="updateItem(index, 'quantity', Number(($event.target as HTMLInputElement).value))"
                />
              </td>
              <td class="px-4 py-2">
                <input
                  v-model.number="item.price"
                  type="number"
                  min="0"
                  step="0.01"
                  :readonly="isLocked"
                  class="w-full rounded bg-bg/50 border border-border/60 px-2 py-1 text-sm text-text"
                  @input="updateItem(index, 'price', Number(($event.target as HTMLInputElement).value))"
                />
              </td>
              <td class="px-4 py-2 font-medium text-text">
                {{ formatMoney(item.total) }} грн
              </td>
              <td v-if="!isLocked" class="px-4 py-2">
                <button
                  type="button"
                  class="text-red-400 hover:text-red-300 transition"
                  @click="removeItem(index)"
                >
                  ✕
                </button>
              </td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-text/60">
                Немає послуг. Додайте послуги з прайсу.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Discount & Totals -->
    <div v-if="!isLocked" class="space-y-3 border-t border-border pt-4">
      <div class="flex items-center gap-3">
        <label class="text-sm text-text/70">Знижка:</label>
        <select
          v-model="discountType"
          class="rounded bg-bg border border-border/60 px-2 py-1 text-sm"
        >
          <option value="percent">%</option>
          <option value="amount">грн</option>
        </select>
        <input
          v-model.number="discount"
          type="number"
          min="0"
          :max="discountType === 'percent' ? 100 : totalAmount"
          step="0.01"
          class="w-32 rounded bg-bg border border-border/60 px-2 py-1 text-sm"
        />
      </div>
    </div>

    <!-- Totals -->
    <div class="border-t border-border pt-4 space-y-2">
      <div class="flex justify-between text-lg font-semibold text-text">
        <span>До сплати:</span>
        <span>{{ formatMoney(totalAmount) }} грн</span>
      </div>
    </div>

    <!-- Additional Fields -->
    <div class="space-y-4">
      <div>
        <label class="block text-xs uppercase text-text/70 mb-1">Опис</label>
        <textarea
          v-model="description"
          :readonly="isLocked"
          rows="2"
          class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          placeholder="Додатковий опис рахунку..."
        />
      </div>
      <div>
        <label class="block text-xs uppercase text-text/70 mb-1">Термін оплати</label>
        <input
          v-model="dueDate"
          type="date"
          :readonly="isLocked"
          class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
        />
      </div>
    </div>

    <!-- Inline mode buttons -->
    <div class="border-t border-border pt-4 flex justify-end gap-3">
      <UIButton variant="ghost" size="sm" @click="emit('cancel')">Скасувати</UIButton>
      <UIButton
        variant="primary"
        size="sm"
        :loading="financeStore.loading"
        :disabled="isLocked && !invoiceId"
        @click="save"
      >
        {{ invoiceId ? 'Оновити' : 'Створити рахунок' }}
      </UIButton>
    </div>
  </div>

  <!-- Modal mode: with Teleport -->
  <Teleport v-else to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4"
        @click.self="open = false"
      >
        <div
          class="w-full max-w-4xl rounded-2xl bg-card text-text shadow-2xl border border-border max-h-[90vh] flex flex-col"
        >
          <div class="p-6 border-b border-border flex items-center justify-between">
            <h2 class="text-xl font-semibold">
              {{ invoiceId ? 'Редагувати рахунок' : 'Створити рахунок' }}
            </h2>
            <button
              type="button"
              class="text-text/70 hover:text-text transition"
              @click="open = false"
            >
              ✕
            </button>
          </div>

          <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">
            <!-- Вибір клініки для супер-адміна -->
            <div v-if="isSuperAdmin" class="space-y-2">
              <label class="block text-xs uppercase text-text/70">Клініка <span class="text-red-400">*</span></label>
              <UISelect
                v-model="selectedClinicId"
                :options="clinicOptions"
                placeholder="Оберіть клініку"
                :disabled="loadingClinics"
              />
            </div>

            <!-- Пацієнт: read-only при редагуванні, select при створенні -->
            <div v-if="invoiceId && patientName" class="space-y-2">
              <label class="block text-xs uppercase text-text/70">Пацієнт</label>
              <div class="px-3 py-2 bg-card/50 border border-border rounded-md text-text">
                {{ patientName }}
              </div>
            </div>
            <div v-else-if="!patientId" class="space-y-2">
              <label class="block text-xs uppercase text-text/70">Пацієнт <span class="text-red-400">*</span></label>
              <UISelect
                v-model="selectedPatientId"
                :options="patientOptions"
                placeholder="Оберіть пацієнта"
                :disabled="loadingPatients"
                searchable
              />
            </div>

            <div v-if="isLocked" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3">
              <p class="text-sm text-yellow-300">
                ⚠️ Редагування заблоковано через наявність оплат. Зробіть повернення коштів для
                розблокування.
              </p>
            </div>

            <!-- Items Table -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-text/90">Послуги</h3>
                <UIButton
                  v-if="!isLocked"
                  variant="secondary"
                  size="sm"
                  @click="showProcedureSearch = !showProcedureSearch"
                >
                  + Додати послугу
                </UIButton>
              </div>

              <!-- Procedure Search -->
              <div v-if="showProcedureSearch && !isLocked" class="relative">
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Пошук по прайсу (назва або код)..."
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  @focus="showProcedureSearch = true"
                />
                <div
                  v-if="searchQuery && filteredProcedures.length > 0"
                  class="absolute z-10 w-full mt-1 bg-card border border-border rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                  <button
                    v-for="proc in filteredProcedures"
                    :key="proc.id"
                    type="button"
                    class="w-full text-left px-3 py-2 hover:bg-card/80 text-sm text-text/80 hover:text-text border-b border-border/60 last:border-0"
                    @click="addItem(proc)"
                  >
                    <div class="font-medium">{{ proc.name }}</div>
                    <div class="text-xs text-text/60">
                      {{ proc.code || '—' }} • {{ formatMoney(proc.price || 0) }} грн
                    </div>
                  </button>
                </div>
              </div>

              <!-- Items Table -->
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead class="bg-card/80 border-b border-border">
                    <tr class="text-left text-text/70">
                      <th class="px-4 py-2">Назва</th>
                      <th class="px-4 py-2 w-24">Кількість</th>
                      <th class="px-4 py-2 w-32">Ціна</th>
                      <th class="px-4 py-2 w-32">Сума</th>
                      <th v-if="!isLocked" class="px-4 py-2 w-16"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(item, index) in items"
                      :key="index"
                      class="border-b border-border/60 hover:bg-card/40"
                    >
                      <td class="px-4 py-2">
                        <input
                          v-model="item.name"
                          type="text"
                          :readonly="isLocked"
                          class="w-full bg-transparent border-0 focus:outline-none text-text"
                          @input="updateItem(index, 'name', ($event.target as HTMLInputElement).value)"
                        />
                      </td>
                      <td class="px-4 py-2">
                        <input
                          v-model.number="item.quantity"
                          type="number"
                          min="1"
                          :readonly="isLocked"
                          class="w-full rounded bg-bg/50 border border-border/60 px-2 py-1 text-sm text-text"
                          @input="updateItem(index, 'quantity', Number(($event.target as HTMLInputElement).value))"
                        />
                      </td>
                      <td class="px-4 py-2">
                        <input
                          v-model.number="item.price"
                          type="number"
                          min="0"
                          step="0.01"
                          :readonly="isLocked"
                          class="w-full rounded bg-bg/50 border border-border/60 px-2 py-1 text-sm text-text"
                          @input="updateItem(index, 'price', Number(($event.target as HTMLInputElement).value))"
                        />
                      </td>
                      <td class="px-4 py-2 font-medium text-text">
                        {{ formatMoney(item.total) }} грн
                      </td>
                      <td v-if="!isLocked" class="px-4 py-2">
                        <button
                          type="button"
                          class="text-red-400 hover:text-red-300 transition"
                          @click="removeItem(index)"
                        >
                          ✕
                        </button>
                      </td>
                    </tr>
                    <tr v-if="items.length === 0">
                      <td colspan="5" class="px-4 py-8 text-center text-text/60">
                        Немає послуг. Додайте послуги з прайсу.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Discount & Totals -->
            <div v-if="!isLocked" class="space-y-3 border-t border-border pt-4">
              <div class="flex items-center gap-3">
                <label class="text-sm text-text/70">Знижка:</label>
                <select
                  v-model="discountType"
                  class="rounded bg-bg border border-border/60 px-2 py-1 text-sm"
                >
                  <option value="percent">%</option>
                  <option value="amount">грн</option>
                </select>
                <input
                  v-model.number="discount"
                  type="number"
                  min="0"
                  :max="discountType === 'percent' ? 100 : totalAmount"
                  step="0.01"
                  class="w-32 rounded bg-bg border border-border/60 px-2 py-1 text-sm"
                />
              </div>
            </div>

            <!-- Totals -->
            <div class="border-t border-border pt-4 space-y-2">
              <div class="flex justify-between text-lg font-semibold text-text">
                <span>До сплати:</span>
                <span>{{ formatMoney(totalAmount) }} грн</span>
              </div>
            </div>

            <!-- Additional Fields -->
            <div class="space-y-4">
              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">Опис</label>
                <textarea
                  v-model="description"
                  :readonly="isLocked"
                  rows="2"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="Додатковий опис рахунку..."
                />
              </div>
              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">Термін оплати</label>
                <input
                  v-model="dueDate"
                  type="date"
                  :readonly="isLocked"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                />
              </div>
            </div>
          </div>

          <div class="p-6 border-t border-border flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="open = false; emit('cancel')">Скасувати</UIButton>
            <UIButton
              variant="primary"
              size="sm"
              :loading="financeStore.loading"
              :disabled="isLocked && !invoiceId"
              @click="save"
            >
              {{ invoiceId ? 'Оновити' : 'Створити рахунок' }}
            </UIButton>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

