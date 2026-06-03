<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from './ui/table'
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from './ui/dropdown-menu';
import { Input } from './ui/input';
import { Checkbox } from './ui/checkbox';
import { Button } from './ui/button';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronDown, ChevronLeft, ChevronRight, Funnel, Search } from 'lucide-vue-next'
import type { TableData, Column } from '../types/table-builder'
import { openModal } from '../useModal';
import { debounce } from 'lodash-es'
import { useTranslations } from '../composables/useTranslations'

const { t } = useTranslations('vue_table_builder_table_translations')

const props = defineProps<{
  table: TableData
  name?: string
}>()

const tableName = computed(() => props.name || 'default')
const columnSelector = computed(() => props.table.columns.some((column) => column.can_be_hidden))

// Column visibility state
const hiddenColumns = ref<Set<string>>(new Set(
  props.table.columns.filter(c => c.hidden).map(c => c.key)
))

const visibleColumns = computed(() =>
  props.table.columns.filter(column => !hiddenColumns.value.has(column.key))
)

function toggleColumn(columnKey: string, visible: boolean) {
  if (visible) {
    hiddenColumns.value.delete(columnKey)
  } else {
    hiddenColumns.value.add(columnKey)
  }
  // Trigger reactivity
  hiddenColumns.value = new Set(hiddenColumns.value)
}

// Filter dropdown open state
const filterDropdownOpen = ref(false)

function handleFilterChange(key: string, value: string) {
  const params = new URLSearchParams(window.location.search)

  if (value) {
    params.set(`filter[${key}]`, value)
  } else {
    params.delete(`filter[${key}]`)
  }

  params.delete('page')
  filterDropdownOpen.value = false

  router.get(window.location.pathname + '?' + params.toString(), {}, {
    preserveState: true,
    preserveScroll: true,
  })
}

// Search state
const searchValue = ref(props.table.searchInputs?.global?.value || '')

// Debounced search handler
const handleSearch = debounce((value: string) => {
  const params = new URLSearchParams(window.location.search)

  if (value) {
    params.set('filter[global]', value)
  } else {
    params.delete('filter[global]')
  }

  params.delete('page')

  router.get(window.location.pathname + '?' + params.toString(), {}, {
    preserveState: true,
    preserveScroll: true,
  })
}, 350)

// Watch for search value changes
watch(searchValue, (newValue) => {
  handleSearch(newValue)
})

function getEffectiveSort(column: Column): 'asc' | 'desc' | false {
    if (column.sorted) return column.sorted as 'asc' | 'desc'

    const sortKey = tableName.value !== 'default' ? `${tableName.value}_sort` : 'sort'
    if (new URLSearchParams(window.location.search).has(sortKey)) return false

    if (!props.table.defaultSort) return false
    const defaultKey = (props.table.defaultSort as string).replace(/^-/, '')
    const defaultDir = (props.table.defaultSort as string).startsWith('-') ? 'desc' : 'asc'
    return column.key === defaultKey ? defaultDir as 'asc' | 'desc' : false
}

function handleSort(column: Column) {
  if (!column.sortable) return

  const currentSort = column.sorted as 'asc' | 'desc' | false
  const newSort = currentSort === 'asc' ? 'desc' : currentSort === 'desc' ? false : 'asc'

  const sortKey = tableName.value && tableName.value !== 'default' ? `${tableName.value}_sort` : 'sort'

  const params = Object.fromEntries(new URLSearchParams(window.location.search)) as Record<string, string>
  if (newSort) {
    params[sortKey] = newSort === 'desc' ? `-${column.key}` : column.key
  } else {
    delete params[sortKey]
  }

  router.get(window.location.pathname, params, {
    preserveState: true,
    preserveScroll: true,
  })
}

function getCellValue(row: any, key: string) {
  return key.split('.').reduce((obj, k) => obj?.[k], row)
}

// Row selection
const rowSelection = ref<Set<number>>(new Set())
const allResultsSelected = ref(false)
const selectedCount = computed(() => rowSelection.value.size)
const bulkActions = computed(() => props.table.bulkActions ?? [])
const allVisibleItemsAreSelected = computed(() =>
  allResultsSelected.value ||
  (props.table.data.length > 0 && selectedCount.value > 0)
)

function isRowSelected(index: number): boolean {
  return rowSelection.value.has(index)
}

function toggleRowSelection(index: number) {
  allResultsSelected.value = false
  const next = new Set(rowSelection.value)
  if (next.has(index)) {
    next.delete(index)
  } else {
    next.add(index)
  }
  rowSelection.value = next
}

function selectCurrentPage() {
  allResultsSelected.value = false
  rowSelection.value = new Set(props.table.data.map((_, i) => i))
}

function selectAllResults() {
  allResultsSelected.value = true
  rowSelection.value = new Set(props.table.data.map((_, i) => i))
}

function resetRowSelection() {
  allResultsSelected.value = false
  rowSelection.value = new Set()
}

function handleRowClick(index: number, e: MouseEvent) {
  if (!props.table.rowLinks || !props.table.rowLinks[index]) return

  const cell = (e.target as HTMLElement).closest('td')
  if (cell) {
    const cellIndex = Array.from(cell.parentElement!.children).indexOf(cell)
    const column = visibleColumns.value[cellIndex]
    if (column && column.clickable === false) return
  }

  const url = props.table.rowLinks[index]

  if (props.table.rowLinkType === 'modal') {
    fetch(url, { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then(openModal)
  } else {
    router.visit(url)
  }
}

function handlePerPageChange(value: string) {
  const perPageKey = tableName.value !== 'default' ? `${tableName.value}_perPage` : 'perPage'
  const params = new URLSearchParams(window.location.search)
  params.set(perPageKey, value)
  params.delete('page')
  router.get(window.location.pathname + '?' + params.toString(), {}, {
    preserveState: true,
    preserveScroll: true,
  })
}

const actionError = ref<string | null>(null)

function performBulkAction(action: any) {
  const ids = Array.from(rowSelection.value).map(i => (props.table.data[i] as any)?.id).filter(Boolean)
  if (ids.length === 0) return

  actionError.value = null

  router.post(action.url, { ids }, {
    preserveScroll: true,
    onSuccess: () => resetRowSelection(),
    onError: (errors) => {
      actionError.value = Object.values(errors)[0] ?? t('vue-table-builder::table.bulk_actions.error')
    },
  })
}
</script>

<template>
  <div>
    <div class="flex items-center gap-4 pt-2 pb-4">
      <div class="flex flex-1 items-center gap-2">
          <!-- Filter dropdown -->
          <DropdownMenu v-if="table.filters && Object.keys(table.filters).length > 0" v-model:open="filterDropdownOpen">
              <DropdownMenuTrigger as-child>
                  <Button variant="outline">
                      <Funnel class="h-4 w-4" />
                  </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-64">
                  <div v-for="(filter, index) in table.filters" :key="index" class="p-2">
                      <label class="mb-2 block text-sm font-medium capitalize">{{ filter.label }}</label>
                      <select
                          class="w-full rounded-md border bg-white px-3 py-2 dark:bg-gray-800"
                          :value="filter.value || ''"
                          @change="(e) => handleFilterChange(filter.key, (e.target as HTMLSelectElement).value)"
                      >
                          <option v-for="(optionLabel, optionValue) in filter.options" :key="optionValue" :value="optionValue">
                              {{ optionLabel }}
                          </option>
                      </select>
                  </div>
              </DropdownMenuContent>
          </DropdownMenu>
          <!-- Search Input -->
          <div v-if="table.searchInputs?.global" class="relative flex-1">
              <Search class="absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                  ref="searchInput"
                  v-model="searchValue"
                  :placeholder="table.searchInputs?.global?.label"
                  class="pl-8"
              />
          </div>
      </div>
      <div class="flex items-center gap-2">
          <DropdownMenu v-if="columnSelector">
              <DropdownMenuTrigger as-child>
                  <Button variant="outline" class="ml-auto">
                      {{ t('vue-table-builder::table.columns') }}
                      <ChevronDown class="ml-2 h-4 w-4" />
                  </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                  <DropdownMenuCheckboxItem
                      v-for="column in table.columns.filter((column) => column.can_be_hidden)"
                      :key="column.key"
                      class="capitalize"
                      :model-value="! hiddenColumns.has(column.key)"
                      @update:model-value="(checked) => toggleColumn(column.key, checked)"
                  >
                      {{ column.label }}
                  </DropdownMenuCheckboxItem>
              </DropdownMenuContent>
          </DropdownMenu>
      </div>
  </div>

  <!-- Bulk Action Error -->
  <div v-if="actionError" class="mb-4 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
      {{ actionError }}
  </div>

  <!-- Bulk Actions Bar -->
  <div
      v-if="table.bulkActions && table.bulkActions.length > 0 && selectedCount > 0"
      class="mb-4 flex items-center justify-between rounded-md border bg-gray-50 p-3 dark:bg-gray-800"
  >
      <div class="flex items-center space-x-2">
          <span class="text-sm font-medium">
            {{
                t('vue-table-builder::table.bulk_actions.selected_simple', {
                    'count': selectedCount,
                    'type': selectedCount === 1 ? t('vue-table-builder::table.row') : t('vue-table-builder::table.rows'),
                })
            }}
          </span>
      </div>
      <div class="flex items-center space-x-2">
          <DropdownMenu>
              <DropdownMenuTrigger as-child>
                  <Button variant="outline" size="sm">
                      {{ t('vue-table-builder::table.bulk_actions.title') }}
                      <ChevronDown class="ml-2 h-4 w-4" />
                  </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                  <DropdownMenuItem v-for="action in bulkActions" :key="action.label" @click="() => performBulkAction(action)">
                      {{ action.label }}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="bulkActions.length > 0" />
                  <DropdownMenuItem @click="resetRowSelection">
                      {{ t('vue-table-builder::table.bulk_actions.clear_selection') }}
                  </DropdownMenuItem>
              </DropdownMenuContent>
          </DropdownMenu>
      </div>
  </div>

    <div class="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead v-if="bulkActions.length > 0" :class="[table.headClass, 'w-10']">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <div class="cursor-pointer">
                    <Checkbox
                      :model-value="allVisibleItemsAreSelected"
                      class="h-4 w-4 pointer-events-none"
                    />
                  </div>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                  <DropdownMenuItem @click="selectedCount > 0 ? resetRowSelection() : selectCurrentPage()">
                    {{ selectedCount > 0 ? t('vue-table-builder::table.bulk_actions.clear_selection') : t('vue-table-builder::table.bulk_actions.select_this_page', { count: String(table.data.length) }) }}
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="table.pagination" @click="selectAllResults">
                    {{ t('vue-table-builder::table.bulk_actions.select_all_results', { total: String(table.pagination.total) }) }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableHead>
            <TableHead
              v-for="column in visibleColumns"
              :key="column.key"
              :class="table.headClass"
            >
              <button
                v-if="column.sortable"
                @click="handleSort(column)"
                class="flex items-center gap-1.5 hover:text-foreground"
                :class="{ 'font-semibold text-foreground': getEffectiveSort(column) }"
                type="button"
              >
                {{ column.label }}
                <ArrowUp v-if="getEffectiveSort(column) === 'asc'" class="h-4 w-4" />
                <ArrowDown v-else-if="getEffectiveSort(column) === 'desc'" class="h-4 w-4" />
                <ArrowUpDown v-else class="h-4 w-4 opacity-40" />
              </button>
              <span v-else>{{ column.label }}</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="!table.data || table.data.length === 0">
            <TableCell
              :colspan="visibleColumns.length"
              class="text-center text-muted-foreground"
            >
                {{ t('vue-table-builder::table.no_results') }}
            </TableCell>
          </TableRow>
          <TableRow
            v-for="(row, index) in table.data"
            :key="index"
            @click="table.rowLinks && table.rowLinks[index] ? handleRowClick(index, $event) : undefined"
            :class="table.rowLinks && table.rowLinks[index] ? 'cursor-pointer hover:bg-muted/50' : ''"
          >
            <TableCell v-if="bulkActions.length > 0" :class="table.cellClass" @click.stop>
              <Checkbox
                :model-value="isRowSelected(index)"
                @update:model-value="() => toggleRowSelection(index)"
                class="h-4 w-4"
              />
            </TableCell>
            <TableCell
              v-for="column in visibleColumns"
              :key="column.key"
              :class="[table.cellClass, column.class]"
              v-html="getCellValue(row, column.key)"
            >
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Pagination -->
    <div
      v-if="table.pagination && table.pagination.last_page > 1"
      class="flex items-center justify-between mt-3"
    >
      <div class="flex items-center gap-3">
          <div v-if="table.perPageOptions && table.perPageOptions.length > 1" class="flex items-center gap-1.5">
              <span class="text-sm text-muted-foreground">{{ t('vue-table-builder::table.pagination.per_page') }}</span>
              <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                      <Button variant="outline" size="sm">
                          {{ table.pagination.per_page }}
                          <ChevronDown class="ml-1 h-3.5 w-3.5" />
                      </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start">
                      <DropdownMenuItem
                          v-for="option in table.perPageOptions"
                          :key="option"
                          @click="handlePerPageChange(String(option))"
                      >
                          {{ option }}
                      </DropdownMenuItem>
                  </DropdownMenuContent>
              </DropdownMenu>
          </div>
          <span class="text-sm text-muted-foreground">
              {{ t('vue-table-builder::table.pagination.showing', {
                'from': table.pagination.from ?? 0,
                'to': table.pagination.to ?? 0,
                'total': table.pagination.total
              }) }}
          </span>
      </div>
      <div class="flex items-center gap-2">
        <Link
          v-if="table.pagination.prev_page_url"
          :href="table.pagination.prev_page_url"
          preserve-state
          preserve-scroll
          class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
        >
          <ChevronLeft class="h-4 w-4" />
          {{ t('vue-table-builder::table.pagination.previous') }}
        </Link>
        <span
          v-else
          class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background h-9 px-3 opacity-50 cursor-not-allowed"
        >
          <ChevronLeft class="h-4 w-4" />
          {{ t('vue-table-builder::table.pagination.previous') }}
        </span>

        <div class="text-sm">
          {{ t('vue-table-builder::table.pagination.page_of', { 'current': table.pagination.current_page, 'last': table.pagination.last_page }) }}
        </div>

        <Link
          v-if="table.pagination.next_page_url"
          :href="table.pagination.next_page_url"
          preserve-state
          preserve-scroll
          class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
        >
          {{ t('vue-table-builder::table.pagination.next') }}
          <ChevronRight class="h-4 w-4" />
        </Link>
        <span
          v-else
          class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background h-9 px-3 opacity-50 cursor-not-allowed"
        >
          {{ t('vue-table-builder::table.pagination.next') }}
          <ChevronRight class="h-4 w-4" />
        </span>
      </div>
    </div>
  </div>
</template>
