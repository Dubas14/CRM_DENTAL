<template>
  <div ref="paginationRef" class="tui-pagination"></div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import Pagination from 'tui-pagination';

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

const paginationRef = ref(null);
let paginationInstance = null;

const syncPagination = () => {
  if (!paginationInstance) return;
  paginationInstance.setTotalItems(props.totalItems);
  if (paginationInstance.setItemsPerPage) {
    paginationInstance.setItemsPerPage(props.itemsPerPage);
  }
  paginationInstance.movePageTo(props.currentPage);
};

onMounted(() => {
  paginationInstance = new Pagination(paginationRef.value, {
    totalItems: props.totalItems,
    itemsPerPage: props.itemsPerPage,
    visiblePages: props.visiblePages,
    page: props.currentPage,
    centerAlign: true,
  });

  paginationInstance.on('afterMove', (evt) => {
    emit('update:currentPage', evt.page);
    emit('change', evt.page);
  });
});

watch(
  () => [props.totalItems, props.itemsPerPage, props.currentPage],
  () => {
    syncPagination();
  }
);

</script>
