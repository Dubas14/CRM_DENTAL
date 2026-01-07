import apiClient from './apiClient'

const paymentApi = {
  list(params?: any) {
    return apiClient.get('/payments', { params })
  },
  create(
    invoiceId: number | string,
    payload: {
      amount: number
      method: 'cash' | 'card' | 'bank_transfer' | 'insurance'
      transaction_id?: string | null
    }
  ) {
    return apiClient.post(`/invoices/${invoiceId}/payments`, payload)
  },
  refund(paymentId: number | string, reason: string) {
    return apiClient.post(`/payments/${paymentId}/refund`, { reason })
  }
}

export default paymentApi
