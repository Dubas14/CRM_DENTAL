<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: { type: Array, default: () => [] }, // [{ label: 'Пн', value: 5 }, ...]
  height: { type: Number, default: 150 }
});

const maxVal = computed(() => Math.max(...props.data.map(d => d.value), 1));
</script>

<template>
  <div class="w-full" :style="{ height: height + 'px' }">
    <div class="h-full flex items-end justify-between gap-2">
      <div
          v-for="(item, i) in data"
          :key="i"
          class="flex flex-col items-center gap-2 flex-1 group h-full justify-end"
      >
        <!-- Стовпчик -->
        <div
            class="w-full max-w-[40px] bg-slate-800 rounded-t-lg relative transition-all duration-500 ease-out group-hover:bg-emerald-500/20 overflow-hidden"
            :style="{ height: (item.value / maxVal * 80) + '%' }"
        >
          <!-- Внутрішній заповнювач (для анімації при наведенні) -->
          <div class="absolute bottom-0 left-0 right-0 bg-emerald-500 transition-all duration-500 h-full opacity-80 group-hover:opacity-100"></div>

          <!-- Тултіп значення -->
          <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap border border-slate-700 shadow-lg z-10">
            {{ item.value }} записів
          </div>
        </div>

        <!-- Підпис -->
        <span class="text-xs text-slate-500 font-medium">{{ item.label }}</span>
      </div>
    </div>
  </div>
</template>