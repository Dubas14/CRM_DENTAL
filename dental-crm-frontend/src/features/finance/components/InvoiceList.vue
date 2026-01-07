<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  UISelect,
  UIDateRangePicker,
  UIButton,
  UIBadge,
  UIConfirmDialog,
  UIDrawer,
  UIDropdown
} from '../../../ui'
import { useFinanceStore } from '../../../stores/useFinanceStore'
import { useAuth } from '../../../composables/useAuth'
import { useToast } from '../../../composables/useToast'
import invoiceApi from '../../../services/invoiceApi'
import PaymentModal from '../../../components/finance/PaymentModal.vue'
import InvoiceForm from '../../../components/finance/InvoiceForm.vue'

const _router = useRouter() // Reserved for navigation
const { user: _user } = useAuth() // Reserved for permissions
const _financeStore = useFinanceStore() // Reserved for state
const { showToast } = useToast()

const invoices = ref<any[]>([])
const loading = ref(false)
const pagination = ref({ page: 1, perPage: 20, total: 0 })
const filters = ref({
  status: '' as string,
  patient_id: null as number | null,
  dateRange: { from: null as string | null, to: null as string | null }
})

// Modals
const showPaymentModal = ref(false)
const selectedInvoiceId = ref<number | null>(null)
const showCancelDialog = ref(false)
const selectedInvoice = ref<any>(null)
const showEditDrawer = ref(false)
const editingInvoice = ref<any>(null)

const statusOptions = [
  { value: '', label: 'Всі статуси' },
  { value: 'unpaid', label: 'Не оплачено' },
  { value: 'partially_paid', label: 'Частково оплачено' },
  { value: 'paid', label: 'Оплачено' },
  { value: 'cancelled', label: 'Скасовано' }
]

const getStatusBadge = (status: string) => {
  const variants: Record<
    string,
    { variant: 'success' | 'warning' | 'danger' | 'default'; label: string }
  > = {
    paid: { variant: 'success', label: 'Оплачено' },
    partially_paid: { variant: 'warning', label: 'Частково' },
    unpaid: { variant: 'danger', label: 'Не оплачено' },
    cancelled: { variant: 'default', label: 'Скасовано' }
  }
  return variants[status] || { variant: 'default', label: status }
}

const formatMoney = (amount: number | string) => {
  return new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(Number(amount) || 0)
}

const isOverdue = (invoice: any) => {
  if (!invoice.due_date) return false
  if (invoice.status === 'paid' || invoice.status === 'cancelled') return false
  return new Date(invoice.due_date) < new Date()
}

const getInvoiceActions = (invoice: any) => {
  const actions: { id: string; label: string; icon?: string }[] = []

  if (Number(invoice.paid_amount) === 0 && invoice.status !== 'cancelled') {
    actions.push({ id: 'edit', label: 'Редагувати', icon: '✏️' })
  }
  if (invoice.status !== 'paid' && invoice.status !== 'cancelled') {
    actions.push({ id: 'pay', label: 'Оплатити', icon: '💳' })
  }
  if (invoice.status !== 'cancelled' && invoice.status !== 'paid') {
    actions.push({ id: 'cancel', label: 'Скасувати', icon: '❌' })
  }

  return actions
}

const handleAction = (action: string, invoice: any) => {
  switch (action) {
    case 'edit':
      openEditDrawer(invoice)
      break
    case 'pay':
      openPaymentModal(invoice)
      break
    case 'cancel':
      openCancelDialog(invoice)
      break
  }
}

const loadInvoices = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.value.page,
      per_page: pagination.value.perPage
    }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.patient_id) params.patient_id = filters.value.patient_id
    if (filters.value.dateRange.from) params.date_from = filters.value.dateRange.from
    if (filters.value.dateRange.to) params.date_to = filters.value.dateRange.to

    const { data } = await invoiceApi.list(params)
    invoices.value = Array.isArray(data?.data) ? data.data : []
    pagination.value = {
      page: data.current_page || 1,
      perPage: data.per_page || 20,
      total: data.total || 0
    }
  } catch (error) {
    console.error('Failed to load invoices:', error)
    showToast('Не вдалося завантажити рахунки', 'error')
  } finally {
    loading.value = false
  }
}

const handlePageChange = (page: number) => {
  pagination.value.page = page
  loadInvoices()
}

const openPaymentModal = (invoice: any) => {
  selectedInvoiceId.value = invoice.id
  showPaymentModal.value = true
}

const onPaymentComplete = () => {
  showPaymentModal.value = false
  loadInvoices()
  showToast('Оплату прийнято', 'success')
}

const openCancelDialog = (invoice: any) => {
  selectedInvoice.value = invoice
  showCancelDialog.value = true
}

const openEditDrawer = (invoice: any) => {
  editingInvoice.value = invoice
  showEditDrawer.value = true
}

const onInvoiceSaved = () => {
  showEditDrawer.value = false
  editingInvoice.value = null
  loadInvoices()
  showToast('Рахунок оновлено', 'success')
}

const cancelInvoice = async () => {
  if (!selectedInvoice.value) return
  try {
    await invoiceApi.cancel(selectedInvoice.value.id)
    showToast('Рахунок скасовано', 'success')
    loadInvoices()
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Не вдалося скасувати рахунок', 'error')
  }
}

onMounted(() => {
  loadInvoices()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Filters -->
    <div class="flex gap-4 items-end flex-wrap">
      <div class="w-48">
        <label class="block text-xs uppercase text-text/70 mb-1">Статус</label>
        <UISelect
          v-model="filters.status"
          :options="statusOptions"
          @update:model-value="loadInvoices"
        />
      </div>
      <div>
        <label class="block text-xs uppercase text-text/70 mb-1">Період</label>
        <UIDateRangePicker v-model="filters.dateRange" @update:model-value="loadInvoices" />
      </div>
      <UIButton variant="secondary" size="sm" @click="loadInvoices"> Оновити </UIButton>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-text/60">Завантаження...</div>

    <!-- Table -->
    <div v-else class="relative">
      <table class="min-w-full border-collapse">
        <thead class="bg-card/80 border-b border-border">
          <tr class="text-left text-xs font-semibold text-text/70 uppercase tracking-wider">
            <th class="px-4 py-3">Номер</th>
            <th class="px-4 py-3">Пацієнт</th>
            <th class="px-4 py-3">Сума</th>
            <th class="px-4 py-3">Сплачено</th>
            <th class="px-4 py-3">Борг</th>
            <th class="px-4 py-3">Статус</th>
            <th class="px-4 py-3">Створено</th>
            <th class="px-4 py-3">Сплатити до</th>
            <th class="px-4 py-3">Клініка</th>
            <th class="px-4 py-3">Дії</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border/60">
          <tr
            v-for="invoice in invoices"
            :key="invoice.id"
            class="hover:bg-card/40 transition-colors"
          >
            <td class="px-4 py-3 text-sm text-text font-medium">
              {{ invoice.invoice_number }}
            </td>
            <td class="px-4 py-3 text-sm text-text">
              {{ invoice.patient?.full_name || '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-text">
              {{ formatMoney(invoice.total_amount || invoice.amount) }} грн
            </td>
            <td class="px-4 py-3 text-sm text-emerald-400">
              {{ formatMoney(invoice.paid_amount) }} грн
            </td>
            <td class="px-4 py-3 text-sm text-red-400">
              {{ formatMoney(invoice.debt_amount) }} грн
            </td>
            <td class="px-4 py-3">
              <UIBadge :variant="getStatusBadge(invoice.status).variant" small>
                {{ getStatusBadge(invoice.status).label }}
              </UIBadge>
            </td>
            <td class="px-4 py-3 text-sm text-text/70">
              {{ new Date(invoice.created_at).toLocaleDateString('uk-UA') }}
            </td>
            <td
              class="px-4 py-3 text-sm"
              :class="isOverdue(invoice) ? 'text-red-400 font-medium' : 'text-text/70'"
            >
              {{ invoice.due_date ? new Date(invoice.due_date).toLocaleDateString('uk-UA') : '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-text/70">
              {{ invoice.clinic?.name || '—' }}
            </td>
            <td class="px-4 py-3">
              <UIDropdown
                :items="getInvoiceActions(invoice)"
                placement="bottom-end"
                @select="(action) => handleAction(action, invoice)"
              >
                <template #trigger="{ toggle }">
                  <UIButton variant="ghost" size="sm" @click.stop="toggle"> ⋮ </UIButton>
                </template>
              </UIDropdown>
            </td>
          </tr>
          <tr v-if="invoices.length === 0">
            <td colspan="10" class="px-4 py-8 text-center text-text/60">Немає рахунків</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > pagination.perPage" class="flex items-center justify-between">
      <div class="text-sm text-text/70">
        Показано {{ (pagination.page - 1) * pagination.perPage + 1 }} -
        {{ Math.min(pagination.page * pagination.perPage, pagination.total) }} з
        {{ pagination.total }}
      </div>
      <div class="flex items-center gap-2">
        <UIButton
          :disabled="pagination.page <= 1"
          variant="secondary"
          size="sm"
          @click="handlePageChange(pagination.page - 1)"
        >
          Назад
        </UIButton>
        <span class="text-sm text-text/70">
          Сторінка {{ pagination.page }} з {{ Math.ceil(pagination.total / pagination.perPage) }}
        </span>
        <UIButton
          :disabled="pagination.page >= Math.ceil(pagination.total / pagination.perPage)"
          variant="secondary"
          size="sm"
          @click="handlePageChange(pagination.page + 1)"
        >
          Вперед
        </UIButton>
      </div>
    </div>

    <!-- Payment Modal -->
    <PaymentModal
      v-if="selectedInvoiceId"
      v-model="showPaymentModal"
      :invoice-id="selectedInvoiceId"
      @paid="onPaymentComplete"
    />

    <!-- Cancel Confirm Dialog -->
    <UIConfirmDialog
      v-model="showCancelDialog"
      title="Скасувати рахунок?"
      :message="`Ви впевнені, що хочете скасувати рахунок ${selectedInvoice?.invoice_number}?`"
      confirm-text="Скасувати рахунок"
      cancel-text="Ні"
      variant="danger"
      @confirm="cancelInvoice"
    />

    <!-- Edit Invoice Modal -->
    <UIDrawer
      v-model="showEditDrawer"
      title="Редагувати рахунок"
      position="center"
      width="600px"
      max-width="95vw"
      resizable
    >
      <InvoiceForm
        v-if="showEditDrawer && editingInvoice"
        inline
        :invoice-id="editingInvoice.id"
        :patient-id="editingInvoice.patient_id"
        @saved="onInvoiceSaved"
        @cancel="showEditDrawer = false"
      />
    </UIDrawer>
  </div>
</template>
