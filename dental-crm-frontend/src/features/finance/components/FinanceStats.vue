<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { UIStatsCard, UISelect, UIButton } from '../../../ui'
import financeApi from '../../../services/financeApi'
import clinicApi from '../../../services/clinicApi'
import { useAuth } from '../../../composables/useAuth'

const { user } = useAuth()
const stats = ref<any>(null)
const loading = ref(false)

// Для супер-адміна
const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin')
const clinics = ref<any[]>([])
const selectedClinicId = ref<number | null>(null)

const clinicOptions = computed(() => {
  return clinics.value.map((c) => ({
    value: c.id,
    label: c.name
  }))
})

const resolvedClinicId = computed(() => {
  if (isSuperAdmin.value) {
    return selectedClinicId.value
  }
  return user.value?.doctor?.clinic_id || user.value?.clinics?.[0]?.id || null
})

const loadClinics = async () => {
  if (!isSuperAdmin.value) return
  try {
    const { data } = await clinicApi.list()
    clinics.value = Array.isArray(data) ? data : data?.data || []
    if (clinics.value.length > 0 && !selectedClinicId.value) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (err) {
    console.error('Failed to load clinics:', err)
  }
}

const loadStats = async () => {
  if (!resolvedClinicId.value) return

  loading.value = true
  try {
    const { data } = await financeApi.getStats(resolvedClinicId.value)
    stats.value = data
  } catch (error) {
    console.error('Failed to load stats:', error)
  } finally {
    loading.value = false
  }
}

// Перезавантажувати при зміні клініки
watch(resolvedClinicId, () => {
  if (resolvedClinicId.value) {
    loadStats()
  }
})

onMounted(async () => {
  if (isSuperAdmin.value) {
    await loadClinics()
  }
  loadStats()
})

// Expose для батьківського компоненту
defineExpose({ loadStats })
</script>

<template>
  <div class="space-y-6">
    <!-- Фільтри для супер-адміна -->
    <div v-if="isSuperAdmin" class="flex items-end gap-4">
      <div class="w-64">
        <label class="block text-xs uppercase text-text/70 mb-1">Клініка</label>
        <UISelect
          v-model="selectedClinicId"
          :options="clinicOptions"
          placeholder="Оберіть клініку"
        />
      </div>
      <UIButton variant="secondary" size="sm" @click="loadStats"> Оновити </UIButton>
    </div>

    <div v-if="loading" class="text-center py-12 text-text/60">Завантаження...</div>
    <div v-else-if="!resolvedClinicId" class="text-center py-12 text-text/60">
      Оберіть клініку для перегляду статистики
    </div>
    <div v-else-if="stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <UIStatsCard
        title="Загальний борг"
        :value="stats.total_debt"
        variant="danger"
        :trend="
          stats.month_trend
            ? {
                value: Math.abs(stats.month_trend),
                direction: stats.month_trend > 0 ? 'up' : 'down',
                label: 'vs минулий місяць'
              }
            : undefined
        "
      />
      <UIStatsCard title="Оплачено сьогодні" :value="stats.paid_today" variant="success" />
      <UIStatsCard title="Оплачено за тиждень" :value="stats.paid_this_week" variant="success" />
      <UIStatsCard
        title="Неоплачених рахунків"
        :value="stats.unpaid_invoices_count"
        variant="warning"
      />
    </div>
  </div>
</template>
