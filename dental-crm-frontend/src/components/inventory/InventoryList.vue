<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useInventoryStore } from '../../stores/useInventoryStore'
import { UIButton, UIBadge } from '../../ui'
import { useAuth } from '../../composables/useAuth'
import InventoryTransaction from './InventoryTransaction.vue'

const { user } = useAuth()
const inventoryStore = useInventoryStore()

const showTransactionModal = ref(false)
const selectedItem = ref<any>(null)

const clinicId = computed(() => {
  return user.value?.doctor?.clinic_id || user.value?.clinics?.[0]?.id
})

const isLowStock = (item: any) => {
  return item.current_stock < item.min_stock_level
}

const formatStock = (item: any) => {
  return `${item.current_stock} ${item.unit}`
}

onMounted(async () => {
  if (clinicId.value) {
    await inventoryStore.fetchItems({ clinic_id: clinicId.value })
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Склад</h1>
        <p class="text-sm text-text/70">Облік матеріалів та залишків</p>
      </div>
      <UIButton variant="secondary" size="sm" @click="showTransactionModal = true">
        + Транзакція
      </UIButton>
    </header>

    <section class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <div v-if="inventoryStore.loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="inventoryStore.error" class="text-sm text-red-400">
        {{ inventoryStore.error }}
      </div>
      <div v-else-if="inventoryStore.items.length === 0" class="text-sm text-text/70">
        Немає матеріалів
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
              <th class="px-4 py-2">Назва</th>
              <th class="px-4 py-2">Артикул</th>
              <th class="px-4 py-2">Залишок</th>
              <th class="px-4 py-2">Мін. рівень</th>
              <th class="px-4 py-2">Статус</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in inventoryStore.items"
              :key="item.id"
              class="border-b border-border/60 hover:bg-card/80 transition-colors"
              :class="{ 'bg-red-500/5': isLowStock(item) }"
            >
              <td class="px-4 py-3 font-medium text-text">{{ item.name }}</td>
              <td class="px-4 py-3 text-text/80">{{ item.code || '—' }}</td>
              <td class="px-4 py-3 text-text/80">{{ formatStock(item) }}</td>
              <td class="px-4 py-3 text-text/80">{{ item.min_stock_level }} {{ item.unit }}</td>
              <td class="px-4 py-3">
                <UIBadge v-if="isLowStock(item)" variant="danger" small> ⚠️ Закінчується </UIBadge>
                <UIBadge v-else variant="success" small>В наявності</UIBadge>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <InventoryTransaction
      v-model="showTransactionModal"
      :clinic-id="clinicId"
      @saved="inventoryStore.fetchItems({ clinic_id: clinicId })"
    />
  </div>
</template>
