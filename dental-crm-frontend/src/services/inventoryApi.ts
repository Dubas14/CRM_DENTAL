import apiClient from './apiClient'
import { buildKey, withCacheAndDedupe } from './requestCache'

const inventoryApi = {
  listItems(params?: any) {
    const key = buildKey('/inventory-items', params || {})
    return withCacheAndDedupe(key, () => apiClient.get('/inventory-items', { params }))
  },
  getItem(id: number | string) {
    return apiClient.get(`/inventory-items/${id}`)
  },
  createItem(payload: {
    clinic_id: number
    name: string
    code?: string | null
    unit: string
    min_stock_level?: number
    current_stock?: number
    initial_stock?: number
  }) {
    return apiClient.post('/inventory-items', payload)
  },
  updateItem(id: number | string, payload: any) {
    return apiClient.put(`/inventory-items/${id}`, payload)
  },
  deleteItem(id: number | string) {
    return apiClient.delete(`/inventory-items/${id}`)
  },
  listTransactions(params?: any) {
    return apiClient.get('/inventory-transactions', { params })
  },
  createTransaction(payload: {
    clinic_id: number
    inventory_item_id: number
    type: 'purchase' | 'usage' | 'adjustment'
    quantity: number
    cost_per_unit?: number | null
    related_entity_type?: string | null
    related_entity_id?: number | null
    note?: string | null
  }) {
    return apiClient.post('/inventory-transactions', payload)
  }
}

export default inventoryApi
