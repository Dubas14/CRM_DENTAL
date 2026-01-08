<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { UIButton } from '../../ui'
import invoiceApi from '../../services/invoiceApi'
import { useToast } from '../../composables/useToast'

const props = defineProps<{
  invoiceId: number | null
  invoiceNumber?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { showToast } = useToast()

const loading = ref(false)
const pdfUrl = ref<string | null>(null)
const error = ref<string | null>(null)

const apiUrl = computed(() => {
  const baseURL = import.meta.env.DEV ? '/api' : import.meta.env.VITE_API_URL || '/api'
  return `${baseURL}/invoices/${props.invoiceId}/pdf`
})

const loadPdf = async () => {
  if (!props.invoiceId) return

  loading.value = true
  error.value = null

  try {
    const response = await invoiceApi.downloadPDF(props.invoiceId)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    pdfUrl.value = url
  } catch (err: any) {
    console.error('Failed to load PDF:', err)
    error.value = err.response?.data?.message || 'Не вдалося завантажити PDF'
    showToast('Не вдалося завантажити PDF', 'error')
  } finally {
    loading.value = false
  }
}

const downloadPdf = async () => {
  if (!props.invoiceId) return

  try {
    const response = await invoiceApi.downloadPDF(props.invoiceId)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `invoice_${props.invoiceNumber || props.invoiceId}_${new Date().toISOString().split('T')[0]}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    showToast('PDF завантажено', 'success')
  } catch (err: any) {
    console.error('Failed to download PDF:', err)
    showToast('Не вдалося завантажити PDF', 'error')
  }
}

const printPdf = () => {
  if (!pdfUrl.value) return

  // Створюємо iframe для друку з правильними налаштуваннями
  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = 'none'
  iframe.src = pdfUrl.value

  document.body.appendChild(iframe)

  iframe.onload = () => {
    setTimeout(() => {
      iframe.contentWindow?.print()
      // Видаляємо iframe після друку
      setTimeout(() => {
        document.body.removeChild(iframe)
      }, 1000)
    }, 500)
  }
}

// Keyboard shortcuts
const handleKeyPress = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && props.invoiceId) {
    emit('close')
  }
  if ((event.ctrlKey || event.metaKey) && event.key === 'p' && props.invoiceId && pdfUrl.value) {
    event.preventDefault()
    printPdf()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeyPress)
})

// Cleanup on unmount
onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyPress)
  if (pdfUrl.value) {
    URL.revokeObjectURL(pdfUrl.value)
  }
})

// Load PDF when component mounts or invoiceId changes
watch(
  () => props.invoiceId,
  (newId) => {
    if (newId) {
      loadPdf()
    } else {
      if (pdfUrl.value) {
        URL.revokeObjectURL(pdfUrl.value)
        pdfUrl.value = null
      }
    }
  },
  { immediate: true }
)

// Cleanup URL when component is closed
watch(
  () => props.invoiceId,
  () => {
    if (!props.invoiceId && pdfUrl.value) {
      URL.revokeObjectURL(pdfUrl.value)
      pdfUrl.value = null
    }
  }
)
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="invoiceId"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        @click.self="emit('close')"
      >
        <div class="w-full max-w-6xl h-[90vh] bg-card rounded-xl shadow-2xl border border-border flex flex-col overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
            <div>
              <h2 class="text-xl font-semibold text-text">Рахунок {{ invoiceNumber || `#${invoiceId}` }}</h2>
              <p class="text-sm text-text/70">Попередній перегляд PDF</p>
            </div>
            <div class="flex items-center gap-2">
              <UIButton variant="primary" size="sm" :disabled="loading || !pdfUrl" @click="downloadPdf">
                <span class="flex items-center gap-1.5">
                  <span>💾</span>
                  <span>Завантажити</span>
                </span>
              </UIButton>
              <UIButton variant="secondary" size="sm" :disabled="loading || !pdfUrl" @click="printPdf">
                <span class="flex items-center gap-1.5">
                  <span>🖨️</span>
                  <span>Друкувати</span>
                </span>
              </UIButton>
              <UIButton variant="ghost" size="sm" @click="emit('close')" title="Закрити (Esc)">✕</UIButton>
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-hidden relative">
            <div v-if="loading" class="absolute inset-0 flex items-center justify-center">
              <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
                <p class="text-text/70">Завантаження PDF...</p>
              </div>
            </div>

            <div v-else-if="error" class="absolute inset-0 flex items-center justify-center">
              <div class="text-center p-6">
                <div class="text-red-400 text-4xl mb-4">⚠️</div>
                <p class="text-text font-medium mb-2">Помилка завантаження</p>
                <p class="text-sm text-text/70 mb-4">{{ error }}</p>
                <UIButton variant="primary" size="sm" @click="loadPdf">Спробувати знову</UIButton>
              </div>
            </div>

            <iframe
              v-else-if="pdfUrl"
              :src="pdfUrl"
              class="w-full h-full border-0"
              type="application/pdf"
            />
          </div>

          <!-- Footer with print settings hint -->
          <div class="px-6 py-3 border-t border-border bg-bg/50 shrink-0">
            <div class="flex items-center justify-between">
              <p class="text-xs text-text/60">
                💡 Для друку: масштаб 100%, прибрати колонтитули
              </p>
              <div class="flex items-center gap-2 text-xs text-text/60">
                <kbd class="px-2 py-1 bg-bg border border-border rounded text-xs">Ctrl+P</kbd>
                <span>або використайте кнопку вище</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>