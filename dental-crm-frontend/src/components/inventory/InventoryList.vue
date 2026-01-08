<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useInventoryStore } from '../../stores/useInventoryStore'
import { UIButton, UIBadge, UITabs, UISelect } from '../../ui'
import { useAuth } from '../../composables/useAuth'
import InventoryTransaction from './InventoryTransaction.vue'
import MaterialCreateModal from './MaterialCreateModal.vue'
import clinicApi from '../../services/clinicApi'

const { user } = useAuth()
const inventoryStore = useInventoryStore()

const activeTab = ref<'stock' | 'history'>('stock')
const showTransactionModal = ref(false)
const showMaterialModal = ref(false)
const editingItemId = ref<number | null>(null)
const selectedItem = ref<any>(null)

// Клініка
const selectedClinicId = ref<number | null>(null)
const clinics = ref<any[]>([])
const loadingClinics = ref(false)

// Перевірка чи супер-адмін
const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin')

// Автоматичне визначення клініки для не-супер-адмінів
const clinicId = computed(() => {
  if (isSuperAdmin.value) {
    return selectedClinicId.value
  }
  return user.value?.doctor?.clinic_id || user.value?.clinics?.[0]?.id || null
})

// Опції для вибору клініки
const clinicOptions = computed(() => {
  return clinics.value.map((c) => ({
    value: c.id,
    label: c.name
  }))
})

// Завантажити клініки для супер-адміна
const loadClinics = async () => {
  if (!isSuperAdmin.value) return

  loadingClinics.value = true
  try {
    const { data } = await clinicApi.list()
    clinics.value = Array.isArray(data) ? data : data?.data || []
    // Автовибір першої клініки
    if (clinics.value.length > 0 && !selectedClinicId.value) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (err) {
    console.error('Failed to load clinics:', err)
  } finally {
    loadingClinics.value = false
  }
}

const isLowStock = (item: any) => {
  return item.current_stock < item.min_stock_level
}

const formatStock = (item: any) => {
  return `${item.current_stock} ${item.unit}`
}

const formatMoney = (amount: number | null | undefined) => {
  if (!amount) return '0.00'
  return new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('uk-UA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getTransactionTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    purchase: 'Прихід',
    usage: 'Списання',
    adjustment: 'Корекція'
  }
  return labels[type] || type
}

const reloadData = async () => {
  if (!clinicId.value) return
  await inventoryStore.fetchItems({ clinic_id: clinicId.value })
  if (activeTab.value === 'history') {
    await inventoryStore.fetchTransactions({ clinic_id: clinicId.value })
  }
}

onMounted(async () => {
  if (isSuperAdmin.value) {
    await loadClinics()
  }
  if (clinicId.value) {
    await reloadData()
  }
})

watch(activeTab, async (newTab) => {
  if (newTab === 'history' && clinicId.value) {
    await inventoryStore.fetchTransactions({ clinic_id: clinicId.value })
  }
})

watch(clinicId, async () => {
  await reloadData()
})

const startEdit = (item: any) => {
  editingItemId.value = item.id
  showMaterialModal.value = true
}

const handleMaterialModalClose = () => {
  editingItemId.value = null
}

const handleDelete = async (item: any) => {
  if (!window.confirm(`Видалити матеріал "${item.name}"?`)) return
  
  try {
    await inventoryStore.deleteItem(item.id)
    await reloadData()
  } catch (err: any) {
    console.error('Failed to delete item:', err)
  }
}

const handleMaterialSaved = () => {
  editingItemId.value = null
  reloadData()
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Склад</h1>
        <p class="text-sm text-text/70">Облік матеріалів та залишків</p>
      </div>
      <div class="flex gap-2">
        <UIButton
          v-if="activeTab === 'stock'"
          variant="secondary"
          size="sm"
          @click="editingItemId = null; showMaterialModal = true"
        >
          + Новий матеріал
        </UIButton>
        <UIButton variant="secondary" size="sm" @click="showTransactionModal = true">
          + Транзакція
        </UIButton>
      </div>
    </header>

    <!-- Вибір клініки для супер-адміна -->
    <div v-if="isSuperAdmin" class="flex items-center gap-3">
      <label class="text-xs uppercase text-text/70">Клініка</label>
      <UISelect
        v-model="selectedClinicId"
        :options="clinicOptions"
        placeholder="Оберіть клініку"
        :disabled="loadingClinics"
        class="w-64"
      />
    </div>

    <UITabs
      v-model="activeTab"
      :tabs="[
        { id: 'stock', label: 'Залишки' },
        { id: 'history', label: 'Історія рухів' }
      ]"
    />

    <!-- Tab: Залишки -->
    <section v-if="activeTab === 'stock'" class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <div v-if="inventoryStore.loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="inventoryStore.error" class="text-sm text-red-400">
        {{ inventoryStore.error }}
      </div>
      <div v-else-if="inventoryStore.items.length === 0" class="text-sm text-text/70">
        Немає матеріалів. Створіть перший матеріал, натиснувши "Новий матеріал".
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
              <th class="px-4 py-2">Назва</th>
              <th class="px-4 py-2">Артикул</th>
              <th class="px-4 py-2">Одиниця</th>
              <th class="px-4 py-2">Поточний залишок</th>
              <th class="px-4 py-2">Мін. залишок</th>
              <th class="px-4 py-2">Статус</th>
              <th class="px-4 py-2 text-right">Дії</th>
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
              <td class="px-4 py-3 text-text/80">{{ item.unit }}</td>
              <td class="px-4 py-3 text-text/80">{{ formatStock(item) }}</td>
              <td class="px-4 py-3 text-text/80">{{ item.min_stock_level }} {{ item.unit }}</td>
              <td class="px-4 py-3">
                <UIBadge v-if="isLowStock(item)" variant="danger" small> ⚠️ Закінчується </UIBadge>
                <UIBadge v-else variant="success" small>В наявності</UIBadge>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <button
                    type="button"
                    class="text-emerald-400 hover:text-emerald-300 text-sm transition"
                    @click="startEdit(item)"
                    title="Редагувати"
                  >
                    Редагувати
                  </button>
                  <button
                    type="button"
                    class="text-red-400 hover:text-red-300 text-sm transition"
                    @click="handleDelete(item)"
                    title="Видалити"
                  >
                    Видалити
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Tab: Історія -->
    <section v-if="activeTab === 'history'" class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <div v-if="inventoryStore.loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="inventoryStore.error" class="text-sm text-red-400">
        {{ inventoryStore.error }}
      </div>
      <div v-else-if="inventoryStore.transactions.length === 0" class="text-sm text-text/70">
        Немає транзакцій
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
              <th class="px-4 py-2">Дата</th>
              <th class="px-4 py-2">Тип</th>
              <th class="px-4 py-2">Матеріал</th>
              <th class="px-4 py-2 text-right">Кількість</th>
              <th class="px-4 py-2 text-right">Ціна</th>
              <th class="px-4 py-2 text-right">Сума</th>
              <th class="px-4 py-2">Хто провів</th>
              <th class="px-4 py-2">Коментар</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="tx in inventoryStore.transactions"
              :key="tx.id"
              class="border-b border-border/60 hover:bg-card/80 transition-colors"
            >
              <td class="px-4 py-3 text-text/80">{{ formatDate(tx.created_at) }}</td>
              <td class="px-4 py-3">
                <UIBadge
                  :variant="tx.type === 'purchase' ? 'success' : tx.type === 'usage' ? 'danger' : 'secondary'"
                  small
                >
                  {{ getTransactionTypeLabel(tx.type) }}
                </UIBadge>
              </td>
              <td class="px-4 py-3 font-medium text-text">{{ tx.item?.name || '—' }}</td>
              <td class="px-4 py-3 text-text/80 text-right">{{ tx.quantity }} {{ tx.item?.unit || '' }}</td>
              <td class="px-4 py-3 text-text/80 text-right">
                {{ tx.cost_per_unit ? formatMoney(tx.cost_per_unit) + ' грн' : '—' }}
              </td>
              <td class="px-4 py-3 text-text/80 text-right font-medium">
                {{ tx.cost_per_unit ? formatMoney(tx.cost_per_unit * tx.quantity) + ' грн' : '—' }}
              </td>
              <td class="px-4 py-3 text-text/80">
                {{ tx.creator ? `${tx.creator.first_name} ${tx.creator.last_name}` : '—' }}
              </td>
              <td class="px-4 py-3 text-text/80">{{ tx.note || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <MaterialCreateModal
      v-model="showMaterialModal"
      :clinic-id="clinicId"
      :item-id="editingItemId"
      @saved="handleMaterialSaved()"
      @close="handleMaterialModalClose()"
    />

    <InventoryTransaction
      v-model="showTransactionModal"
      :clinic-id="clinicId"
      @saved="reloadData()"
    />
  </div>
</template>
