<script setup>
import { computed } from 'vue';
import { Activity } from 'lucide-vue-next';

const props = defineProps({
  data: {
    type: Array,
    default: () => []
  },
  title: {
    type: String,
    default: 'Активність за тиждень'
  }
});

const maxValue = computed(() => {
  if (!props.data.length) return 0;
  return props.data.reduce((max, item) => Math.max(max, item.value || 0), 0);
});

const normalizedData = computed(() => {
  const max = maxValue.value || 1;
  return props.data.map(item => ({
    ...item,
    value: item.value || 0,
    height: Math.round(((item.value || 0) / max) * 100)
  }));
});
</script>

<template>
  <div class="bg-card border border-border rounded-xl p-6 space-y-4 h-full">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-text">
        <Activity size="20" class="text-emerald-400" />
        <h3 class="text-lg font-bold">{{ title }}</h3>
      </div>
      <p class="text-sm text-text/60" v-if="maxValue">Максимум: {{ maxValue }}</p>
    </div>

    <div v-if="normalizedData.length" class="grid gap-4">
      <div class="flex items-end gap-4 h-48">
        <div
            v-for="item in normalizedData"
            :key="item.day"
            class="flex-1 flex flex-col items-center gap-2"
        >
          <span class="text-sm font-semibold text-text">{{ item.value }}</span>
          <div class="w-full h-36 bg-card/60 rounded-lg overflow-hidden flex items-end border border-border/60">
            <div
                class="w-full bg-gradient-to-t from-emerald-600 via-emerald-400 to-emerald-200 rounded-lg transition-all duration-300 shadow-lg shadow-emerald-900/30"
                :style="{ height: item.height + '%' }"
                :title="`${item.day}: ${item.value}`"
            ></div>
          </div>
          <span class="text-xs uppercase tracking-wide text-text/70">{{ item.day }}</span>
        </div>
      </div>
    </div>

    <div v-else class="text-center text-text/60 text-sm py-8">
      Немає даних для відображення активності.
    </div>
  </div>
</template>