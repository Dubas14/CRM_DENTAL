<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import patientFileApi from '../../services/patientFileApi'
import { UIButton, UIBadge } from '../../ui'
import { useToast } from '../../composables/useToast'

interface PatientFile {
  id: number
  file_path: string
  file_name: string
  file_type: 'xray' | 'photo' | 'contract' | 'anamnesis'
  created_at: string
  uploaded_by?: number
}

const props = defineProps<{
  patientId: number
}>()

const { showToast } = useToast()

const files = ref<PatientFile[]>([])
const loading = ref(false)
const uploading = ref(false)
const lightboxOpen = ref(false)
const lightboxImage = ref<string | null>(null)
const dragActive = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)

const fileTypeLabels = {
  xray: 'Рентген',
  photo: 'Фото',
  contract: 'Договір',
  anamnesis: 'Анамнез'
}

const fileTypeIcons = {
  xray: '📷',
  photo: '🖼️',
  contract: '📄',
  anamnesis: '📋'
}

const imageTypes = ['xray', 'photo']
const isImage = (fileType: string) => imageTypes.includes(fileType)

const loadFiles = async () => {
  loading.value = true
  try {
    const { data } = await patientFileApi.list(props.patientId)
    files.value = Array.isArray(data) ? data : []
  } catch (err: any) {
    showToast('Не вдалося завантажити файли', 'error')
  } finally {
    loading.value = false
  }
}

const handleFileSelect = async (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files && input.files.length > 0) {
    await uploadFiles(Array.from(input.files))
    input.value = ''
  }
}

const openFileDialog = () => {
  fileInputRef.value?.click()
}

const handleDrop = async (event: DragEvent) => {
  event.preventDefault()
  dragActive.value = false

  if (event.dataTransfer?.files) {
    await uploadFiles(Array.from(event.dataTransfer.files))
  }
}

const uploadFiles = async (fileList: File[]) => {
  uploading.value = true
  try {
    for (const file of fileList) {
      // Determine file type from extension or default to photo
      let fileType: 'xray' | 'photo' | 'contract' | 'anamnesis' = 'photo'
      const ext = file.name.split('.').pop()?.toLowerCase()
      if (['jpg', 'jpeg', 'png', 'gif'].includes(ext || '')) {
        fileType = 'photo'
      } else if (['pdf'].includes(ext || '')) {
        fileType = 'contract'
      }

      const { data } = await patientFileApi.upload(props.patientId, file, fileType)
      // Оптимістично додаємо файл у список для прев'ю без повторного запиту
      files.value.unshift(data)
    }
    showToast('Файли завантажено успішно', 'success')
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Не вдалося завантажити файли', 'error')
  } finally {
    uploading.value = false
  }
}

const deleteFile = async (fileId: number) => {
  if (!confirm('Видалити цей файл?')) return

  try {
    await patientFileApi.delete(props.patientId, fileId)
    showToast('Файл видалено', 'success')
    await loadFiles()
  } catch (err: any) {
    showToast('Не вдалося видалити файл', 'error')
  }
}

const openLightbox = (file: PatientFile) => {
  if (isImage(file.file_type)) {
    lightboxImage.value = getFileUrl(file)
    lightboxOpen.value = true
  }
}

const closeLightbox = () => {
  lightboxOpen.value = false
  lightboxImage.value = null
}

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

const getFileUrl = (file: PatientFile) => {
  if (!file?.file_path) return ''
  if (file.file_path.startsWith('http')) return file.file_path
  if (file.file_path.startsWith('/storage')) {
    return `${backendOrigin}${file.file_path}`
  }
  const sanitized = file.file_path.replace(/^\/+/, '')
  return `${backendOrigin}/storage/${sanitized}`
}

const downloadFile = (file: PatientFile) => {
  const url = getFileUrl(file)
  if (!url) return

  const a = document.createElement('a')
  a.href = url
  a.download = file.file_name || 'file'
  a.target = '_blank'
  a.rel = 'noopener noreferrer'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

onMounted(loadFiles)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-text/90">Файли пацієнта</h3>
      <div class="flex items-center gap-2">
        <UIButton variant="secondary" size="sm" :loading="uploading" @click="openFileDialog">
          {{ uploading ? 'Завантаження...' : '+ Завантажити файл' }}
        </UIButton>
        <input
          ref="fileInputRef"
          type="file"
          multiple
          accept="image/*,.pdf"
          class="hidden"
          @change="handleFileSelect"
        />
      </div>
    </div>

    <!-- Dropzone -->
    <div
      class="border-2 border-dashed rounded-xl p-8 text-center transition cursor-pointer"
      :class="
        dragActive
          ? 'border-emerald-500 bg-emerald-500/10'
          : 'border-border/60 hover:border-border'
      "
      @click="openFileDialog"
      @drop.prevent="handleDrop"
      @dragover.prevent="dragActive = true"
      @dragleave.prevent="dragActive = false"
    >
      <p class="text-text/70 mb-2">Перетягніть файли сюди або натисніть для вибору</p>
      <p class="text-xs text-text/60">Підтримуються: JPG, PNG, PDF</p>
    </div>

    <!-- Files List -->
    <div v-if="loading" class="text-center py-8 text-text/70">Завантаження...</div>
    <div v-else-if="files.length === 0" class="text-center py-8 text-text/60">
      Немає файлів
    </div>
    <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <div
        v-for="file in files"
        :key="file.id"
        class="bg-card/60 border border-border rounded-lg p-3 hover:bg-card/80 transition cursor-pointer"
        @click="openLightbox(file)"
      >
        <div class="aspect-square bg-bg/40 rounded mb-2 flex items-center justify-center">
          <img
            v-if="isImage(file.file_type)"
            :src="getFileUrl(file)"
            :alt="file.file_name"
            class="w-full h-full object-cover rounded"
          />
          <div v-else class="text-4xl">{{ fileTypeIcons[file.file_type] }}</div>
        </div>
        <div class="space-y-1">
          <p class="text-xs font-medium text-text truncate" :title="file.file_name">
            {{ file.file_name }}
          </p>
          <div class="flex items-center justify-between">
            <UIBadge variant="info" small>{{ fileTypeLabels[file.file_type] }}</UIBadge>
            <div class="flex items-center gap-2">
              <UIButton
                variant="ghost"
                size="xs"
                class="px-2 text-emerald-300 hover:text-emerald-200"
                @click.stop="downloadFile(file)"
              >
                Завантажити
              </UIButton>
              <UIButton
                variant="ghost"
                size="xs"
                class="px-2 text-red-400 hover:text-red-300"
                @click.stop="deleteFile(file.id)"
              >
                Видалити
              </UIButton>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="lightboxOpen && lightboxImage"
          class="fixed inset-0 z-[3000] bg-black/90 flex items-center justify-center p-4"
          @click="closeLightbox"
        >
          <button
            type="button"
            class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300"
            @click="closeLightbox"
          >
            ✕
          </button>
          <img
            :src="lightboxImage"
            alt="Preview"
            class="max-w-full max-h-full object-contain"
            @click.stop
          />
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

