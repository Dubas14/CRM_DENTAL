<template>
  <div ref="gridRef" class="base-grid"></div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Grid from 'tui-grid'

const props = defineProps({
  columns: {
    type: Array,
    required: true
  },
  data: {
    type: Array,
    default: () => []
  },
  options: {
    type: Object,
    default: () => ({})
  }
})

const gridRef = ref(null)
let gridInstance = null

onMounted(() => {
  gridInstance = new Grid({
    el: gridRef.value,
    data: props.data,
    columns: props.columns,
    scrollX: false,
    scrollY: false,
    bodyHeight: 'auto',
    rowHeaders: [],
    usageStatistics: false,
    columnOptions: {
      resizable: true
    },
    ...props.options
  })
})

watch(
  () => props.data,
  (nextData) => {
    gridInstance?.resetData(nextData)
  },
  { deep: true }
)

onBeforeUnmount(() => {
  gridInstance?.destroy()
})
</script>
