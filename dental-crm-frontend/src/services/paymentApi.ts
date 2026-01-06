import apiClient from './apiClient'

const paymentApi = {
  list(invoiceId: number | string) {
    return apiClient.get(`/invoices/${invoiceId}/payments`)
  },
  create(invoiceId: number | string, payload: {
    amount: number
    method: 'cash' | 'card' | 'bank_transfer' | 'insurance'
    transaction_id?: string | null
  }) {
    return apiClient.post(`/invoices/${invoiceId}/payments`, payload)
  }
}

export default paymentApi

