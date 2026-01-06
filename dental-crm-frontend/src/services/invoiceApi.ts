import apiClient from './apiClient'
import { buildKey, withCacheAndDedupe } from './requestCache'

const invoiceApi = {
  list(params?: any) {
    const key = buildKey('/invoices', params || {})
    return withCacheAndDedupe(key, () => apiClient.get('/invoices', { params }))
  },
  get(id: number | string) {
    return apiClient.get(`/invoices/${id}`)
  },
  create(payload: {
    clinic_id: number
    patient_id: number
    appointment_id?: number | null
    items: Array<{
      procedure_id?: number | null
      name: string
      quantity: number
      price: number
      total: number
    }>
    description?: string
    due_date?: string | null
  }) {
    return apiClient.post('/invoices', payload)
  },
  addItems(invoiceId: number | string, items: Array<{
    procedure_id?: number | null
    name: string
    quantity: number
    price: number
    total: number
  }>) {
    return apiClient.post(`/invoices/${invoiceId}/items`, { items })
  },
  update(id: number | string, payload: any) {
    return apiClient.put(`/invoices/${id}`, payload)
  },
  delete(id: number | string) {
    return apiClient.delete(`/invoices/${id}`)
  }
}

export default invoiceApi

