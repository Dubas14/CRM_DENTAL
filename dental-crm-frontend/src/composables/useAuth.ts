import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from './useToast'
import authApi from '../services/authApi'

// Глобальні стани
const user = ref(null)
const loadingUser = ref(false)

// щоб не робити restoreSession по 10 разів
const _sessionInitPromise = ref(null)

export function useAuth() {
  const router = useRouter()
  const { showToast } = useToast()

  const isLoggedIn = computed(() => !!user.value)

  const login = async (email, password) => {
    try {
      loadingUser.value = true
      const result = await authApi.login(email, password)

      user.value = result

      showToast('Успішний вхід!', 'success')
      return result
    } catch (error) {
      const message = error.message || 'Помилка входу'
      showToast(message, 'error')
      throw error
    } finally {
      loadingUser.value = false
    }
  }

  const logout = async () => {
    try {
      await authApi.logout()
      user.value = null

      showToast('Ви вийшли з системи', 'info')
      await router.push({ name: 'login' })
    } catch (error) {
      console.warn('Logout error:', error)
      user.value = null

      if (typeof window !== 'undefined') {
        localStorage.removeItem('auth_token')
      }

      await router.push({ name: 'login' })
    }
  }

  const fetchUser = async () => {
    try {
      loadingUser.value = true
      const userData = await authApi.getCurrentUser()
      user.value = userData
      return userData
    } catch (error) {
      console.error('Fetch user error:', error)
      user.value = null
      throw error
    } finally {
      loadingUser.value = false
    }
  }

  const restoreSession = async () => {
    if (typeof window === 'undefined') return

    const token = localStorage.getItem('auth_token')
    if (token && !user.value) {
      try {
        await fetchUser()
      } catch (error) {
        console.warn('Cannot restore session:', error)
        localStorage.removeItem('auth_token')
      }
    }
  }

  // ✅ ОДНОРАЗОВА ініціалізація (викликати з App.vue або layout)
  const initAuth = async () => {
    if (_sessionInitPromise.value) return _sessionInitPromise.value

    _sessionInitPromise.value = (async () => {
      await restoreSession()
    })()

    return _sessionInitPromise.value
  }

  return {
    user,
    isLoggedIn,
    loadingUser,
    login,
    logout,
    fetchUser,
    restoreSession,
    initAuth
  }
}
