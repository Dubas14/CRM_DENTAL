<script setup lang="ts">
import { computed } from 'vue'
import { UIButton, UIAvatar, UIBadge } from '../ui'

const props = defineProps<{
  assistant: any | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'details', id: number | string): void
}>()

const fullName = computed(() => {
  const a = props.assistant
  if (!a) return ''
  return a.full_name || a.name || `${a.first_name || ''} ${a.last_name || ''}`.trim() || a.email
})

const avatarUrl = computed(() => props.assistant?.avatar_url || null)
const primaryClinic = computed(() => props.assistant?.clinics?.[0] || null)

const statusVariant = computed(() => 'success')
const statusLabel = computed(() => 'Активний')

const handleDetails = () => {
  if (!props.assistant) return
  emit('details', props.assistant.id)
}
</script>

<template>
  <div v-if="assistant" class="space-y-6">
    <header class="flex items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <UIAvatar :src="avatarUrl || ''" :fallback-text="fullName?.[0] || '?'" :size="72" />
        <div>
          <h2 class="text-lg font-semibold text-text">
            {{ fullName }}
          </h2>
          <p class="text-xs text-text/60 mt-1">Асистент</p>
          <div class="mt-2">
            <UIBadge :variant="statusVariant" small>
              {{ statusLabel }}
            </UIBadge>
          </div>
        </div>
      </div>
      <button class="text-text/60 hover:text-text" type="button" @click="emit('close')">✕</button>
    </header>

    <section class="space-y-3 text-sm">
      <div>
        <p class="text-xs text-text/60 uppercase mb-1">Клініка</p>
        <p class="text-text/90">
          {{ primaryClinic ? primaryClinic.name : '—' }}
        </p>
      </div>

      <div>
        <p class="text-xs text-text/60 uppercase mb-1">Email</p>
        <p class="text-text/90">{{ assistant.email }}</p>
      </div>
    </section>

    <footer class="flex justify-end gap-3 pt-2">
      <UIButton variant="ghost" size="sm" @click="emit('close')">Закрити</UIButton>
      <UIButton variant="secondary" size="sm" @click="handleDetails">Деталі</UIButton>
    </footer>
  </div>
</template>
