<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { ArrowUpDown, ChevronDown, ChevronLeft, ChevronRight, Funnel, Search } from 'lucide-vue-next'
import type { TableData, Column } from '@/types/table-builder'
import { trans } from 'laravel-vue-i18n';

const props = defineProps<{
  table: TableData
  name?: string
}>()
console.log(props.table)
const tableName = computed(() => props.name || 'default')
const columnSelector = computed(() => props.table.columns.some((column) => column.can_be_hidden))

function handleSort(column: Column) {
  if (!column.sortable) return

  const currentSort = column.sorted
  const newSort = currentSort === 'asc' ? 'desc' : currentSort === 'desc' ? false : 'asc'
  
  const sortParam = newSort ? (newSort === 'desc' ? `-${column.key}` : column.key) : undefined
  
  router.get(window.location.pathname, {
    ...Object.fromEntries(new URLSearchParams(window.location.search)),
    sort: sortParam,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

function getCellValue(row: any, key: string) {
  return key.split('.').reduce((obj, k) => obj?.[k], row)
}
</script>

<template>
  <div>
    <div class="flex items-center gap-4 pt-2 pb-4">
      <div class="flex flex-1 items-center gap-2">
          <!-- Filter dropdown -->
          <DropdownMenu v-if="table.filters && Object.keys(table.filters).length > 0">
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
                          :value="activeFilters?.[label.toLowerCase().replace(/\s+/g, '_')] || ''"
                          @change="(e) => onFilterChange?.(label.toLowerCase().replace(/\s+/g, '_'), (e.target as HTMLSelectElement).value)"
                      >
                          <option value="">{{ trans('table.all') }}</option>
                          <option v-for="(label, value) in filter.options" :key="filter.key" :value="filter.key">
                              {{ label }}
                          </option>
                      </select>
                  </div>
              </DropdownMenuContent>
          </DropdownMenu>
          <!-- Search Input -->
          <div v-if="table.searchInputs" class="relative flex-1">
              <Search class="absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                  ref="searchInput"
                  v-model="searchValue"
                  :placeholder="table.searchInputs[0].label"
                  class="pl-8"
              />
          </div>
      </div>
      <div class="flex items-center gap-2">
          <DropdownMenu v-if="columnSelector">
              <DropdownMenuTrigger as-child>
                  <Button variant="outline" class="ml-auto">
                      {{ trans('table.columns') }}
                      <ChevronDown class="ml-2 h-4 w-4" />
                  </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                  <DropdownMenuCheckboxItem
                      v-for="column in table.columns.filter((column) => column.can_be_hidden)"
                      :key="column.id"
                      class="capitalize"
                      :model-value="! column.hidden"
                      @update:model-value="
                          (value) => {
                              column.hidden = !!value;
                              console.log(column.hidden, !!value)
                          }
                      "
                  >
                      {{ column.label }}
                  </DropdownMenuCheckboxItem>
              </DropdownMenuContent>
          </DropdownMenu>
      </div>
  </div>

  <!-- Bulk Actions Bar -->
  <div
      v-if="table.bulkActions && selectedCount > 0"
      class="mb-4 flex items-center justify-between rounded-md border bg-gray-50 p-3 dark:bg-gray-800"
  >
      <div class="flex items-center space-x-2">
          <span class="text-sm font-medium">
              {{
                  trans('messages.bulk_actions.selected_simple', {
                      count: selectedCount,
                      type: selectedCount === 1 ? trans('messages.table.row') : trans('messages.table.rows'),
                  })
              }}
          </span>
      </div>
      <div class="flex items-center space-x-2">
          <DropdownMenu>
              <DropdownMenuTrigger as-child>
                  <Button variant="outline" size="sm">
                      {{ trans('messages.bulk_actions.title') }}
                      <ChevronDown class="ml-2 h-4 w-4" />
                  </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                  <DropdownMenuItem v-for="action in bulkActions" :key="action.label" @click="() => action.onClick(selectedRows)">
                      {{ action.label }}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="bulkActions.length > 0" />
                  <DropdownMenuItem @click="() => table.resetRowSelection()">
                      {{ trans('messages.bulk_actions.clear_selection') }}
                  </DropdownMenuItem>
              </DropdownMenuContent>
          </DropdownMenu>
      </div>
  </div>

    <div class="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead
              v-for="column in table.columns"
              :key="column.key"
              :class="column.headerClass"
            >
              <button
                v-if="column.sortable"
                @click="handleSort(column)"
                class="flex items-center gap-2 hover:text-foreground"
                type="button"
              >
                {{ column.label }}
                <ArrowUpDown class="h-4 w-4" />
              </button>
              <span v-else>{{ column.label }}</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="!table.data || table.data.length === 0">
            <TableCell
              :colspan="table.columns.length"
              class="text-center text-muted-foreground"
            >
              No results found.
            </TableCell>
          </TableRow>
          <TableRow v-for="(row, index) in table.data" :key="index">
            <TableCell
              v-for="column in table.columns"
              :key="column.key"
              :class="column.class"
            >
              {{ getCellValue(row, column.key) }}
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
      <div class="text-sm text-muted-foreground">
        Showing {{ table.pagination.from ?? 0 }} to {{ table.pagination.to ?? 0 }} of
        {{ table.pagination.total }} results
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
          Previous
        </Link>
        <span
          v-else
          class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background h-9 px-3 opacity-50 cursor-not-allowed"
        >
          <ChevronLeft class="h-4 w-4" />
          Previous
        </span>

        <div class="text-sm">
          Page {{ table.pagination.current_page }} of {{ table.pagination.last_page }}
        </div>

        <Link
          v-if="table.pagination.next_page_url"
          :href="table.pagination.next_page_url"
          preserve-state
          preserve-scroll
          class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
        >
          Next
          <ChevronRight class="h-4 w-4" />
        </Link>
        <span
          v-else
          class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background h-9 px-3 opacity-50 cursor-not-allowed"
        >
          Next
          <ChevronRight class="h-4 w-4" />
        </span>
      </div>
    </div>
  </div>
</template>
