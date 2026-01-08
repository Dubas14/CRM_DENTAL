<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import clinicLogoApi from '../../services/clinicLogoApi'
import { UIButton } from '../../ui'
import { useToast } from '../../composables/useToast'
import { Upload } from 'lucide-vue-next'

interface Props {
  clinicId: number
  currentLogoUrl?: string | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'uploaded', url: string): void
  (e: 'deleted'): void
}>()

const { showToast } = useToast()

const uploading = ref(false)
const dragActive = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)
const logoUrl = ref<string | null>(props.currentLogoUrl || null)

const backendOrigin = (() => {
  const apiUrl = import.meta.env.VITE_API_URL || ''
  try {
    if (apiUrl) {
      return new URL(apiUrl, window.location.origin).origin
    }
  } catch (_) {
    // ignore parse errors
  }
  return window.location.origin
})()

const getLogoUrl = (url: string | null) => {
  if (!url) return null
  if (url.startsWith('http')) return url
  if (url.startsWith('/storage')) {
    return `${backendOrigin}${url}`
  }
  const sanitized = url.replace(/^\/+/, '')
  return `${backendOrigin}/storage/${sanitized}`
}

const displayedLogoUrl = computed(() => getLogoUrl(logoUrl.value))

const handleFileSelect = async (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files && input.files.length > 0) {
    await uploadLogo(input.files[0])
    input.value = ''
  }
}

const openFileDialog = () => {
  fileInputRef.value?.click()
}

const handleDrop = async (event: DragEvent) => {
  event.preventDefault()
  dragActive.value = false

  if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
    await uploadLogo(event.dataTransfer.files[0])
  }
}

const uploadLogo = async (file: File) => {
  // Validate file type
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
  if (!allowedTypes.includes(file.type)) {
    showToast('Невірний формат файлу. Підтримуються тільки зображення (JPG, PNG, GIF, WebP)', 'error')
    return
  }

  // Validate file size (max 10MB)
  if (file.size > 10 * 1024 * 1024) {
    showToast('Розмір файлу перевищує 10MB', 'error')
    return
  }

  uploading.value = true
  try {
    const { data } = await clinicLogoApi.upload(props.clinicId, file)
    logoUrl.value = data.logo_url
    showToast('Логотип завантажено успішно', 'success')
    emit('uploaded', data.logo_url)
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Не вдалося завантажити логотип', 'error')
  } finally {
    uploading.value = false
  }
}

const deleteLogo = async () => {
  if (!confirm('Видалити логотип?')) return

  uploading.value = true
  try {
    await clinicLogoApi.delete(props.clinicId)
    logoUrl.value = null
    showToast('Логотип видалено', 'success')
    emit('deleted')
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Не вдалося видалити логотип', 'error')
  } finally {
    uploading.value = false
  }
}

// Watch for external changes to currentLogoUrl
watch(() => props.currentLogoUrl, (newUrl) => {
  logoUrl.value = newUrl || null
})
</script>

<template>
  <div class="space-y-4">
    <label class="block text-xs uppercase text-text/70 mb-1">Логотип клініки</label>

    <!-- Current Logo Preview -->
    <div v-if="displayedLogoUrl" class="flex items-start gap-4">
      <div class="w-32 h-32 bg-bg border border-border rounded-lg overflow-hidden flex items-center justify-center">
        <img
          :src="displayedLogoUrl"
          alt="Логотип клініки"
          class="w-full h-full object-contain"
        />
      </div>
      <div class="flex-1 space-y-2">
        <p class="text-sm text-text/70">Поточний логотип</p>
        <UIButton
          variant="danger"
          size="sm"
          :disabled="uploading"
          @click="deleteLogo"
        >
          Видалити
        </UIButton>
      </div>
    </div>

    <!-- Dropzone -->
    <div
      class="border-2 border-dashed rounded-xl p-6 text-center transition cursor-pointer"
      :class="
        dragActive
          ? 'border-emerald-500 bg-emerald-500/10'
          : uploading
            ? 'border-border/40 bg-bg/40 cursor-not-allowed'
            : 'border-border/60 hover:border-border'
      "
      @click="!uploading && openFileDialog()"
      @drop.prevent="handleDrop"
      @dragover.prevent="dragActive = true"
      @dragleave.prevent="dragActive = false"
    >
      <input
        ref="fileInputRef"
        type="file"
        accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
        class="hidden"
        :disabled="uploading"
        @change="handleFileSelect"
      />
      <Upload :size="32" class="mx-auto mb-2 text-text/70" />
      <p class="text-text/70 mb-1">
        {{ uploading ? 'Завантаження...' : displayedLogoUrl ? 'Замінити логотип' : 'Завантажити логотип' }}
      </p>
      <p class="text-xs text-text/60">
        {{ uploading ? 'Будь ласка, зачекайте...' : 'Перетягніть файл сюди або натисніть для вибору' }}
      </p>
      <p class="text-xs text-text/50 mt-1">JPG, PNG, GIF, WebP (макс. 10MB)</p>
    </div>
  </div>
</template>
