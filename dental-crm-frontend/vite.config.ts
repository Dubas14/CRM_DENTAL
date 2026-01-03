import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  css: {
    lightningcss: {
      errorRecovery: true
    }
  },
  // 👇 ОСЬ ЦЕЙ БЛОК НАМ ПОТРІБЕН
  server: {
    host: '0.0.0.0', // Дозволяє доступ з будь-якої IP (в т.ч. з Windows)
    port: 5173, // Жорстко фіксуємо порт, щоб він не скакав на 5174
    strictPort: true, // Якщо порт зайнятий — видати помилку, а не змінювати його
    hmr: {
      host: 'localhost' // Допомагає з оновленням сторінки (Hot Reload)
    },
    // Proxy API requests to backend to avoid CORS in development
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true
      }
    }
  }
})
