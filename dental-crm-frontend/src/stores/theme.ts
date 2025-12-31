import { defineStore } from 'pinia'
import { ref } from 'vue'

const THEME_STORAGE_KEY = 'theme'
const SUPPORTED_THEMES = ['light', 'dark', 'clinic']

export const useThemeStore = defineStore('theme', () => {
  const theme = ref('light')

  const applyThemeClass = (value) => {
    if (typeof document === 'undefined') return
    const root = document.documentElement
    SUPPORTED_THEMES.forEach((item) => root.classList.remove(item))
    root.classList.add(value)
  }

  const setTheme = (value) => {
    if (!SUPPORTED_THEMES.includes(value)) return
    theme.value = value
    applyThemeClass(value)

    if (typeof window !== 'undefined') {
      window.localStorage.setItem(THEME_STORAGE_KEY, value)
    }
  }

  const initTheme = () => {
    if (typeof window === 'undefined') {
      setTheme('light')
      return
    }

    const savedTheme = window.localStorage.getItem(THEME_STORAGE_KEY)
    const nextTheme = SUPPORTED_THEMES.includes(savedTheme) ? savedTheme : 'light'
    setTheme(nextTheme)
  }

  return {
    theme,
    setTheme,
    initTheme
  }
})
