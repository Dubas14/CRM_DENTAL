<script setup lang="ts">
import { ref, computed } from 'vue'
import { useVueTable, getCoreRowModel, type ColumnDef, type SortingState, type PaginationState } from '@tanstack/vue-table'

interface Props {
  data: any[]
  columns: ColumnDef<any>[]
  loading?: boolean
  pagination?: {
    page: number
    perPage: number
    total: number
  }
  sorting?: SortingState
}

interface Emits {
  (e: 'sort', sorting: SortingState): void
  (e: 'page-change', pagination: { page: number; perPage: number }): void
  (e: 'row-click', row: any): void
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  sorting: () => [],
})

const emit = defineEmits<Emits>()

// Server-side pagination state
const paginationState = computed<PaginationState>(() => ({
  pageIndex: (props.pagination?.page ?? 1) - 1, // TanStack uses 0-based index
  pageSize: props.pagination?.perPage ?? 20,
}))

// Server-side sorting state
const sortingState = ref<SortingState>(props.sorting)

const table = useVueTable({
  get data() {
    return props.data
  },
  columns: props.columns,
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true, // Server-side pagination
  manualSorting: true, // Server-side sorting
  pageCount: props.pagination ? Math.ceil(props.pagination.total / props.pagination.perPage) : 0,
  state: {
    pagination: paginationState.value,
    sorting: sortingState.value,
  },
  onPaginationChange: (updater) => {
    const newPagination = typeof updater === 'function' ? updater(paginationState.value) : updater
    emit('page-change', {
      page: newPagination.pageIndex + 1, // Convert back to 1-based
      perPage: newPagination.pageSize,
    })
  },
  onSortingChange: (updater) => {
    const newSorting = typeof updater === 'function' ? updater(sortingState.value) : updater
    sortingState.value = newSorting
    emit('sort', newSorting)
  },
})

const totalPages = computed(() => {
  if (!props.pagination) return 0
  return Math.ceil(props.pagination.total / props.pagination.perPage)
})
</script>

<template>
  <div class="w-full">
    <!-- Loading overlay -->
    <div v-if="loading" class="absolute inset-0 bg-black/20 backdrop-blur-sm z-10 flex items-center justify-center">
      <div class="text-text/70">Завантаження...</div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full border-collapse">
        <thead class="bg-card/80 border-b border-border">
          <tr v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <th
              v-for="header in headerGroup.headers"
              :key="header.id"
              :class="[
                'px-4 py-3 text-left text-xs font-semibold text-text/70 uppercase tracking-wider',
                header.column.getCanSort() && 'cursor-pointer select-none hover:bg-card/60',
              ]"
              @click="header.column.getToggleSortingHandler()?.($event)"
            >
              <div class="flex items-center gap-2">
                <slot :name="`header-${header.id}`" :header="header">
                  {{ header.isPlaceholder ? null : header.column.columnDef.header }}
                </slot>
                <span v-if="header.column.getCanSort()" class="text-text/50">
                  {{
                    header.column.getIsSorted() === 'asc'
                      ? '↑'
                      : header.column.getIsSorted() === 'desc'
                        ? '↓'
                        : '↕'
                  }}
                </span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border/60">
          <tr
            v-for="row in table.getRowModel().rows"
            :key="row.id"
            class="hover:bg-card/40 transition-colors cursor-pointer"
            @click="emit('row-click', row.original)"
          >
            <td
              v-for="cell in row.getVisibleCells()"
              :key="cell.id"
              class="px-4 py-3 text-sm text-text"
            >
              <slot :name="`cell-${cell.column.id}`" :cell="cell" :row="row">
                {{ cell.getValue() }}
              </slot>
            </td>
          </tr>
          <tr v-if="table.getRowModel().rows.length === 0">
            <td :colspan="table.getAllColumns().length" class="px-4 py-8 text-center text-text/60">
              Немає даних
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="props.pagination && totalPages > 1" class="mt-4 flex items-center justify-between">
      <div class="text-sm text-text/70">
        Показано {{ (props.pagination.page - 1) * props.pagination.perPage + 1 }} -
        {{ Math.min(props.pagination.page * props.pagination.perPage, props.pagination.total) }} з
        {{ props.pagination.total }}
      </div>
      <div class="flex items-center gap-2">
        <button
          :disabled="!table.getCanPreviousPage()"
          class="px-3 py-1 rounded border border-border bg-card text-text disabled:opacity-50 disabled:cursor-not-allowed hover:bg-card/80"
          @click="table.previousPage()"
        >
          Назад
        </button>
        <span class="text-sm text-text/70">
          Сторінка {{ props.pagination.page }} з {{ totalPages }}
        </span>
        <button
          :disabled="!table.getCanNextPage()"
          class="px-3 py-1 rounded border border-border bg-card text-text disabled:opacity-50 disabled:cursor-not-allowed hover:bg-card/80"
          @click="table.nextPage()"
        >
          Вперед
        </button>
      </div>
    </div>
  </div>
</template>

