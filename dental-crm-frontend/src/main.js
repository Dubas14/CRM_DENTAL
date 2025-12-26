import { createApp } from 'vue'
import { createPinia } from 'pinia'

// ✅ TUI CSS — ПЕРШИМИ
import 'tui-date-picker/dist/tui-date-picker.css'
import 'tui-time-picker/dist/tui-time-picker.css'
import 'tui-grid/dist/tui-grid.css'
import '@toast-ui/calendar/dist/toastui-calendar.css'

// ✅ твій кастомний theme для календаря
import './assets/css/toast-calendar-theme.css'

// ✅ Tailwind — ПІСЛЯ
import './assets/main.css'

import App from './App.vue'
import router from './router'
import { useThemeStore } from './stores/theme'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

const themeStore = useThemeStore(pinia)
themeStore.initTheme()

app.mount('#app')