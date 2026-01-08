import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import invoiceApi from '../services/invoiceApi'
import paymentApi from '../services/paymentApi'
import procedureApi from '../services/procedureApi'
import { useToast } from '../composables/useToast'

interface InvoiceItem {
  id?: number
  procedure_id?: number | null
  name: string
  quantity: number
  price: number
  total: number
}

interface Invoice {
  id: number
  clinic_id: number
  patient_id: number
  appointment_id?: number | null
  invoice_number: string
  status: 'unpaid' | 'partially_paid' | 'paid' | 'cancelled' | 'refunded'
  description?: string | null
  due_date?: string | null
  items?: InvoiceItem[]
  payments?: any[]
  total_amount: number
  paid_amount: number
  debt_amount: number
  created_at: string
  updated_at: string
}

interface Procedure {
  id: number
  name: string
  price?: number | null
  code?: string | null
  category?: string
  duration_minutes?: number
}

export const useFinanceStore = defineStore('finance', () => {
  const { showToast } = useToast()

  const invoices = ref<Invoice[]>([])
  const currentInvoice = ref<Invoice | null>(null)
  const procedures = ref<Procedure[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Computed
  const proceduresMap = computed(() => {
    const map = new Map<number, Procedure>()
    procedures.value.forEach((p) => {
      map.set(p.id, p)
    })
    return map
  })

  // Actions
  const fetchProcedures = async (clinicId?: number) => {
    try {
      const params: any = {}
      if (clinicId) params.clinic_id = clinicId
      const { data } = await procedureApi.list(params)
      const fetched = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      
      // Дедуплікація за ID перед додаванням
      const uniqueProcedures = fetched.filter((p, index, self) => 
        index === self.findIndex(proc => proc.id === p.id)
      )
      
      // Якщо clinicId передано, повністю замінити список процедурами цієї клініки
      if (clinicId) {
        procedures.value = uniqueProcedures.filter(p => !p.clinic_id || p.clinic_id === clinicId)
      } else {
        // Без clinicId: дедуплікувати та додати нові або оновити існуючі
        const existingIds = new Set(procedures.value.map(p => p.id))
        const newProcedures = uniqueProcedures.filter(p => !existingIds.has(p.id))
        
        // Оновити існуючі
        uniqueProcedures.forEach(updated => {
          const index = procedures.value.findIndex(p => p.id === updated.id)
          if (index !== -1) {
            procedures.value[index] = updated
          }
        })
        
        // Додати нові
        procedures.value.push(...newProcedures)
      }
    } catch (err: any) {
      console.error('Failed to fetch procedures:', err)
      showToast('Не вдалося завантажити прайс', 'error')
    }
  }

  const fetchInvoice = async (id: number | string) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await invoiceApi.get(id)
      currentInvoice.value = data
      return data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося завантажити рахунок'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const createInvoice = async (payload: {
    clinic_id: number
    patient_id: number
    appointment_id?: number | null
    items: InvoiceItem[]
    description?: string
    due_date?: string | null
  }) => {
    loading.value = true
    error.value = null
    try {
      // Validate items
      if (!payload.items || payload.items.length === 0) {
        throw new Error('Додайте хоча б одну послугу')
      }

      const total = payload.items.reduce((sum, item) => sum + item.total, 0)
      if (total <= 0) {
        throw new Error('Сума рахунку повинна бути більше 0')
      }

      const { data } = await invoiceApi.create(payload)
      currentInvoice.value = data
      showToast('Рахунок створено успішно', 'success')
      return data
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Не вдалося створити рахунок'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const addItems = async (invoiceId: number | string, items: InvoiceItem[]) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await invoiceApi.addItems(invoiceId, items)
      if (currentInvoice.value?.id === Number(invoiceId)) {
        currentInvoice.value = data
      }
      showToast('Послуги додано', 'success')
      return data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося додати послуги'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const addPayment = async (
    invoiceId: number | string,
    payload: {
      amount: number
      method: 'cash' | 'card' | 'bank_transfer' | 'insurance'
      transaction_id?: string | null
    }
  ) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await paymentApi.create(invoiceId, payload)

      // Refresh invoice to get updated totals
      if (currentInvoice.value?.id === Number(invoiceId)) {
        await fetchInvoice(invoiceId)
      }

      const statusMessages: Record<string, string> = {
        unpaid: 'Рахунок не оплачено',
        partially_paid: 'Рахунок частково оплачено',
        paid: 'Рахунок оплачено повністю'
      }

      const invoiceStatus = currentInvoice.value?.status || data.invoice?.status
      const message = statusMessages[invoiceStatus] || 'Оплату прийнято успішно'
      showToast(message, 'success')
      return data
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Не вдалося прийняти оплату'
      error.value = errorMessage
      showToast(errorMessage, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const listInvoices = async (params?: any) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await invoiceApi.list(params)
      invoices.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      return invoices.value
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося завантажити рахунки'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const resetCurrentInvoice = () => {
    currentInvoice.value = null
    error.value = null
  }

  return {
    invoices,
    currentInvoice,
    procedures,
    loading,
    error,
    proceduresMap,
    fetchProcedures,
    fetchInvoice,
    createInvoice,
    addItems,
    addPayment,
    listInvoices,
    resetCurrentInvoice
  }
})
