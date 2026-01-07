<script setup lang="ts">
import { ref } from 'vue'
import { useFinanceStore } from '../../stores/useFinanceStore'
import { useAuth } from '../../composables/useAuth'
import { UITabs, UIButton, UIDrawer } from '../../ui'
import InvoiceList from './components/InvoiceList.vue'
import FinanceStats from './components/FinanceStats.vue'
import InvoiceForm from '../../components/finance/InvoiceForm.vue'

const { user: _user } = useAuth() // Reserved for permissions
const _financeStore = useFinanceStore() // Reserved for state

const activeTab = ref<'invoices' | 'stats' | 'reports'>('invoices')
const showCreateInvoice = ref(false)
const invoiceListRef = ref<InstanceType<typeof InvoiceList> | null>(null)

const onInvoiceCreated = () => {
  showCreateInvoice.value = false
  // Reload invoice list
  if (invoiceListRef.value && typeof (invoiceListRef.value as any).loadInvoices === 'function') {
    ;(invoiceListRef.value as any).loadInvoices()
  }
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-text">Фінанси</h1>
      <UIButton
        v-if="activeTab === 'invoices'"
        variant="primary"
        size="sm"
        @click="showCreateInvoice = true"
      >
        + Створити рахунок
      </UIButton>
    </div>

    <UITabs
      v-model="activeTab"
      :tabs="[
        { id: 'invoices', label: 'Рахунки' },
        { id: 'stats', label: 'Статистика' },
        { id: 'reports', label: 'Звіти' }
      ]"
    />

    <div v-if="activeTab === 'invoices'">
      <InvoiceList ref="invoiceListRef" />
    </div>

    <div v-if="activeTab === 'stats'">
      <FinanceStats />
    </div>

    <div v-if="activeTab === 'reports'">
      <div class="text-center py-12 text-text/60">Розділ звітів в розробці</div>
    </div>

    <!-- Create Invoice Modal -->
    <UIDrawer
      v-model="showCreateInvoice"
      title="Новий рахунок"
      position="center"
      width="600px"
      max-width="95vw"
      resizable
    >
      <InvoiceForm
        v-if="showCreateInvoice"
        inline
        @created="onInvoiceCreated"
        @saved="onInvoiceCreated"
        @cancel="showCreateInvoice = false"
      />
    </UIDrawer>
  </div>
</template>
