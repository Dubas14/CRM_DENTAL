import apiClient from './apiClient'

const financeApi = {
  getStats(clinicId?: number) {
    const params = clinicId ? { clinic_id: clinicId } : {}
    return apiClient.get('/finance/stats', { params })
  },
  invalidateStatsCache(clinicId: number) {
    return apiClient.post('/finance/stats/invalidate', { clinic_id: clinicId })
  },
}

export default financeApi

