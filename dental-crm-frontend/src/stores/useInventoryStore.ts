import { defineStore } from 'pinia'
import { ref } from 'vue'
import inventoryApi from '../services/inventoryApi'
import { useToast } from '../composables/useToast'

interface InventoryItem {
  id: number
  clinic_id: number
  name: string
  code?: string | null
  unit: string
  current_stock: number
  min_stock_level: number
  is_active: boolean
  created_at: string
  updated_at: string
}

interface InventoryTransaction {
  id: number
  clinic_id: number
  inventory_item_id: number
  type: 'purchase' | 'usage' | 'adjustment'
  quantity: number
  cost_per_unit?: number | null
  related_entity_type?: string | null
  related_entity_id?: number | null
  note?: string | null
  created_by?: number | null
  created_at: string
  item?: InventoryItem
}

export const useInventoryStore = defineStore('inventory', () => {
  const { showToast } = useToast()

  const items = ref<InventoryItem[]>([])
  const transactions = ref<InventoryTransaction[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchItems = async (params?: any) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await inventoryApi.listItems(params)
      items.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      return items.value
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося завантажити матеріали'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchTransactions = async (params?: any) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await inventoryApi.listTransactions(params)
      transactions.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      return transactions.value
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося завантажити транзакції'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const createItem = async (payload: {
    clinic_id: number
    name: string
    code?: string | null
    unit: string
    min_stock_level?: number
    current_stock?: number
    initial_stock?: number
  }) => {
    loading.value = true
    error.value = null
    try {
      const apiPayload: any = {
        clinic_id: payload.clinic_id,
        name: payload.name,
        code: payload.code || null,
        unit: payload.unit,
        min_stock_level: payload.min_stock_level || 0
      }
      
      // If initial_stock is provided, use it; otherwise use current_stock
      if (payload.initial_stock !== undefined && payload.initial_stock > 0) {
        apiPayload.initial_stock = payload.initial_stock
      } else if (payload.current_stock !== undefined) {
        apiPayload.current_stock = payload.current_stock
      }
      
      const { data } = await inventoryApi.createItem(apiPayload)
      items.value.push(data)
      showToast('Матеріал створено успішно', 'success')
      return data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося створити матеріал'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateItem = async (id: number | string, payload: any) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await inventoryApi.updateItem(id, payload)
      const index = items.value.findIndex((item) => item.id === Number(id))
      if (index !== -1) {
        items.value[index] = data
      }
      // Toast показується в компоненті
      return data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося оновити матеріал'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteItem = async (id: number | string) => {
    loading.value = true
    error.value = null
    try {
      await inventoryApi.deleteItem(id)
      items.value = items.value.filter((item) => item.id !== Number(id))
      showToast('Матеріал видалено', 'success')
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Не вдалося видалити матеріал'
      showToast(error.value, 'error')
      throw err
    } finally {
      loading.value = false
    }
  }

  const addTransaction = async (payload: {
    clinic_id: number
    inventory_item_id: number
    type: 'purchase' | 'usage' | 'adjustment'
    quantity: number
    cost_per_unit?: number | null
    related_entity_type?: string | null
    related_entity_id?: number | null
    note?: string | null
  }) => {
    loading.value = true
    error.value = null
    try {
      // Frontend validation for usage
      if (payload.type === 'usage') {
        const item = items.value.find((i) => i.id === payload.inventory_item_id)
        if (item && item.current_stock < payload.quantity) {
          const errorMsg = `Неможливо списати: на складі всього ${item.current_stock} ${item.unit}`
          error.value = errorMsg
          showToast(errorMsg, 'error')
          throw new Error(errorMsg)
        }
      }

      const { data } = await inventoryApi.createTransaction(payload)

      // Update item stock in local state from backend response
      if (data.item) {
        const itemIndex = items.value.findIndex((i) => i.id === data.item.id)
        if (itemIndex !== -1) {
          items.value[itemIndex].current_stock = data.item.current_stock
        }
      }

      transactions.value.unshift(data)

      const messages: Record<string, string> = {
        purchase: 'Прихід товару зафіксовано',
        usage: 'Матеріал списано',
        adjustment: 'Корекцію зафіксовано'
      }
      showToast(messages[payload.type] || 'Транзакцію створено', 'success')
      return data
    } catch (err: any) {
      if (!error.value) {
        error.value = err.response?.data?.message || 'Не вдалося створити транзакцію'
        showToast(error.value, 'error')
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    items,
    transactions,
    loading,
    error,
    fetchItems,
    fetchTransactions,
    createItem,
    updateItem,
    deleteItem,
    addTransaction
  }
})
