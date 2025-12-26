<template>
  <div class="flex flex-wrap items-center justify-center gap-2 py-3">
    <button
      type="button"
      class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-1.5 text-sm text-text transition hover:bg-card/70 disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="safeCurrentPage === 1"
      @click="goToPage(safeCurrentPage - 1)"
    >
      Попередня
    </button>

    <button
      v-for="page in pagesToShow"
      :key="page"
      type="button"
      class="inline-flex min-w-[40px] items-center justify-center rounded-lg border px-3 py-1.5 text-sm transition"
      :class="pageButtonClass(page)"
      @click="goToPage(page)"
    >
      {{ page }}
    </button>

    <button
      type="button"
      class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-1.5 text-sm text-text transition hover:bg-card/70 disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="safeCurrentPage === totalPages"
      @click="goToPage(safeCurrentPage + 1)"
    >
      Наступна
    </button>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
  totalItems: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
  currentPage: {
    type: Number,
    default: 1,
  },
  visiblePages: {
    type: Number,
    default: 5,
  },
});

const emit = defineEmits(['update:currentPage', 'change']);

const totalPages = computed(() => {
  const pages = Math.ceil(props.totalItems / props.itemsPerPage);
  return Math.max(pages || 1, 1);
});

const safeCurrentPage = computed(() => {
  return Math.min(Math.max(props.currentPage, 1), totalPages.value);
});

const pagesToShow = computed(() => {
  const visible = Math.max(props.visiblePages, 1);
  const half = Math.floor(visible / 2);
  let start = Math.max(1, safeCurrentPage.value - half);
  let end = Math.min(totalPages.value, start + visible - 1);

  if (end - start + 1 < visible) {
    start = Math.max(1, end - visible + 1);
  }

  return Array.from({ length: end - start + 1 }, (_, idx) => start + idx);
});

const goToPage = (page) => {
  const nextPage = Math.min(Math.max(page, 1), totalPages.value);
  if (nextPage === props.currentPage) return;
  emit('update:currentPage', nextPage);
  emit('change', nextPage);
};

const pageButtonClass = (page) => {
  if (page === safeCurrentPage.value) {
    return 'border-accent bg-accent text-card';
  }
  return 'border-border bg-card text-text hover:bg-card/70';
};

watch(
  () => [props.currentPage, totalPages.value],
  ([page, pages]) => {
    if (page > pages) {
      goToPage(pages);
    }
  }
);
</script>
