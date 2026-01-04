<script setup lang="ts">
import { computed } from 'vue'
import { UIAvatar, UIBadge, UIDropdown, UIButton } from '../../../ui'
import type { Doctor } from '../types'

const props = defineProps<{
  doctor: Doctor | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'details', id: number): void
  (e: 'action', payload: { id: string; doctorId: number | undefined }): void
}>()

const quickActions = [
  { id: 'schedule', label: 'Запланувати прийом', icon: '📅' },
  { id: 'reminder', label: 'Додати нагадування', icon: '⏰' },
  { id: 'email', label: 'Надіслати email', icon: '✉️' },
  { id: 'message', label: 'Повідомлення (Telegram/Email)', icon: '💬' },
  { id: 'call', label: 'Дзвінок', icon: '📞' }
]

const statusVariant = computed(() => {
  if (props.doctor?.status === 'vacation') return 'warning'
  if (props.doctor?.is_active === false || props.doctor?.status === 'inactive') return 'neutral'
  return 'success'
})
</script>

<template>
  <div v-if="doctor" class="space-y-5">
    <div class="flex items-start gap-4">
      <UIAvatar :src="doctor.avatar_url || ''" :fallback-text="doctor.full_name?.[0] || '?'" :size="88" />
      <div class="flex-1">
        <div class="flex items-center gap-3">
          <p class="text-xl font-semibold text-text leading-tight">{{ doctor.full_name }}</p>
          <UIBadge :variant="statusVariant" small>
            {{ doctor.status === 'vacation' ? 'Відпустка' : doctor.is_active === false ? 'Неактивний' : 'Активний' }}
          </UIBadge>
        </div>
        <p class="text-sm text-text/70 mt-1">{{ doctor.specialization || 'Спеціалізація не вказана' }}</p>
      </div>
      <button class="text-text/60 hover:text-text" aria-label="Закрити" @click="emit('close')">✕</button>
    </div>

    <div class="space-y-3 text-sm text-text/80">
      <div class="flex items-start gap-3">
        <span class="text-text/60 w-28">Клініка</span>
        <span>
          {{
            doctor.clinics?.length
              ? doctor.clinics.map((c) => c.name).join(', ')
              : doctor.clinic?.name || '—'
          }}
        </span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-text/60 w-28">Кабінет</span>
        <span>{{ doctor.room || '—' }}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-text/60 w-28">Телефон</span>
        <span>{{ doctor.phone || '—' }}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-text/60 w-28">Email</span>
        <span>{{ doctor.email || '—' }}</span>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-text/60 w-28">Адреса</span>
        <span class="flex flex-col">
          <span>
            {{ doctor.address || '—' }}
            <template v-if="doctor.city">, {{ doctor.city }}</template>
            <template v-if="doctor.state">, {{ doctor.state }}</template>
            <template v-if="doctor.zip">, {{ doctor.zip }}</template>
          </span>
        </span>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <UIDropdown :items="quickActions" @select="(id) => emit('action', { id, doctorId: doctor?.id })">
        <template #trigger="{ toggle }">
          <UIButton variant="secondary" size="sm" @click.stop="toggle">Дії ▾</UIButton>
        </template>
      </UIDropdown>
      <UIButton variant="primary" size="sm" @click="emit('details', doctor.id)">Details</UIButton>
    </div>
  </div>
</template>

