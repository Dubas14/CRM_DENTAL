import apiClient from './apiClient'

// Cache for clinics list
let clinicsCache: { data: any; timestamp: number } | null = null
let clinicsMineCache: { data: any; timestamp: number } | null = null
const CACHE_TTL = 5 * 60 * 1000 // 5 minutes

// Pending requests to prevent duplicate concurrent requests
let pendingListRequest: Promise<any> | null = null
let pendingListMineRequest: Promise<any> | null = null

const clinicApi = {
  list() {
    // Check cache first
    if (clinicsCache && Date.now() - clinicsCache.timestamp < CACHE_TTL) {
      return Promise.resolve({ data: clinicsCache.data })
    }
    
    // If request is already pending, return the same promise
    if (pendingListRequest) {
      return pendingListRequest
    }
    
    // Create new request
    pendingListRequest = apiClient.get('/clinics').then(response => {
      // Cache the result
      clinicsCache = {
        data: response.data,
        timestamp: Date.now()
      }
      pendingListRequest = null
      return response
    }).catch(error => {
      pendingListRequest = null
      throw error
    })
    
    return pendingListRequest
  },
  
  listMine() {
    // Check cache first
    if (clinicsMineCache && Date.now() - clinicsMineCache.timestamp < CACHE_TTL) {
      return Promise.resolve({ data: clinicsMineCache.data })
    }
    
    // If request is already pending, return the same promise
    if (pendingListMineRequest) {
      return pendingListMineRequest
    }
    
    // Create new request
    pendingListMineRequest = apiClient.get('/me/clinics').then(response => {
      // Cache the result
      clinicsMineCache = {
        data: response.data,
        timestamp: Date.now()
      }
      pendingListMineRequest = null
      return response
    }).catch(error => {
      pendingListMineRequest = null
      throw error
    })
    
    return pendingListMineRequest
  },
  
  // Clear cache (useful after creating/updating/deleting clinics)
  clearCache() {
    clinicsCache = null
    clinicsMineCache = null
    pendingListRequest = null
    pendingListMineRequest = null
  }
}

export default clinicApi
