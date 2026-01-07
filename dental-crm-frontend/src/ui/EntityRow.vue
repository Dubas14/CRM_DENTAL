<script setup lang="ts">
import { computed } from 'vue'
import { UIAvatar, UIBadge, UIButton, UIDropdown } from './index'

export interface EntityRowProps {
  id: number | string
  name: string
  avatarUrl?: string | null
  subtitle?: string
  status?: 'active' | 'inactive' | 'vacation' | string
  isActive?: boolean
  clinics?: Array<{ id: number | string; name: string }> | null
  clinic?: { id: number | string; name: string } | null
  actions?: Array<{ id: string; label: string; icon?: string }>
  onAction?: (id: string) => void
  onManage?: () => void
  onDetails?: () => void
  showActions?: boolean
  showManage?: boolean
  showDetails?: boolean
}

const props = withDefaults(defineProps<EntityRowProps>(), {
  avatarUrl: null,
  subtitle: '',
  status: 'active',
  isActive: true,
  clinics: null,
  clinic: null,
  actions: () => [],
  showActions: true,
  showManage: true,
  showDetails: true
})

const emit = defineEmits<{
  (e: 'action', id: string): void
  (e: 'manage'): void
  (e: 'details'): void
  (e: 'click'): void
}>()

const statusVariant = computed(() => {
  if (props.status === 'vacation') return 'warning'
  if (props.isActive === false || props.status === 'inactive') return 'neutral'
  return 'success'
})

const statusLabel = computed(() => {
  if (props.status === 'vacation') return 'Відпустка'
  if (props.isActive === false || props.status === 'inactive') return 'Неактивний'
  return 'Активний'
})

const clinicsTooltip = computed(() => {
  if (props.clinics?.length) {
    return props.clinics.map((c) => c.name).join(', ')
  }
  return props.clinic?.name || '—'
})

const clinicsDisplay = computed(() => {
  if (props.clinics?.length) {
    return `${props.clinics.length} клін.`
  }
  return props.clinic?.name || '—'
})

const handleAction = (actionId: string) => {
  emit('action', actionId)
  if (props.onAction) {
    props.onAction(actionId)
  }
}

const handleManage = () => {
  emit('manage')
  if (props.onManage) {
    props.onManage()
  }
}

const handleDetails = () => {
  emit('details')
  if (props.onDetails) {
    props.onDetails()
  }
}
</script>

<template>
  <tr
    class="border-t border-border/60 hover:bg-card/80 cursor-pointer transition-colors"
    @click="emit('click')"
  >
    <td class="px-4 py-3">
      <div class="flex items-center gap-3">
        <UIAvatar :src="avatarUrl || ''" :fallback-text="name?.[0] || '?'" size="sm" />
        <div>
          <p class="font-semibold text-text">{{ name }}</p>
          <p v-if="subtitle" class="text-xs text-text/60">{{ subtitle }}</p>
        </div>
      </div>
    </td>
    <td class="px-4 py-3 text-text/80">
      <span
        v-if="clinics || clinic"
        class="cursor-help underline decoration-dotted"
        :title="clinicsTooltip"
      >
        {{ clinicsDisplay }}
      </span>
      <span v-else>—</span>
    </td>
    <td v-if="subtitle" class="px-4 py-3 text-text/80">
      {{ subtitle }}
    </td>
    <td class="px-4 py-3">
      <UIBadge :variant="statusVariant" small>
        {{ statusLabel }}
      </UIBadge>
    </td>
    <td class="px-4 py-3 text-right">
      <div class="flex items-center justify-end gap-2" @click.stop>
        <UIDropdown
          v-if="showActions && actions && actions.length > 0"
          :items="actions"
          placement="bottom-end"
          @select="handleAction"
        >
          <template #trigger="{ toggle }">
            <UIButton variant="secondary" size="sm" @click="toggle">Дії ▾</UIButton>
          </template>
        </UIDropdown>
        <UIButton v-if="showManage" variant="primary" size="sm" @click="handleManage">
          Керувати
        </UIButton>
        <UIButton v-if="showDetails" variant="ghost" size="sm" @click="handleDetails">
          Деталі
        </UIButton>
      </div>
    </td>
  </tr>
</template>
