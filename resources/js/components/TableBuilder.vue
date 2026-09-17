<script setup lang="ts">
import {router} from '@inertiajs/vue3'
import {computed, ref, watch} from 'vue'
import {Table, TableBody, TableCell, TableHead, TableHeader, TableRow,} from './ui/table'
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from './ui/dropdown-menu';
import {Input} from './ui/input';
import {Checkbox} from './ui/checkbox';
import {Button} from './ui/button';
import {ArrowDown, ArrowUp, ArrowUpDown, Check, ChevronDown, Funnel, Search, X} from 'lucide-vue-next'
import type {Column, TableData} from '../types/table-builder'
import {useDebounceFn} from '@vueuse/core'
import {useTranslations} from '../composables/useTranslations'
import {useTableTransport, type TableAdapter, type TableFetcher, type TableTransport} from '../composables/useTableTransport'
import TablePagination from './TablePagination.vue'

const {t} = useTranslations('vue_table_builder_table_translations')

const props = withDefaults(defineProps<{
    table: TableData
    name?: string
    only?: string[]
    transport?: TableTransport
    paginationPosition?: 'top' | 'bottom' | 'both'
    source?: string
    fetcher?: TableFetcher
    adapter?: TableAdapter
}>(), {
    transport: 'inertia',
    paginationPosition: 'bottom',
})

const tableName = computed(() => props.name || props.table?.name || 'default')

const {
    table,
    query,
    loading,
    error: transportError,
    sortFor,
    applySort,
    applyFilter,
    applySearch,
    applyPerPage,
    applyPage,
    reload,
} = useTableTransport({
    table: computed(() => props.table),
    transport: computed(() => props.transport),
    source: computed(() => props.source),
    name: tableName,
    only: props.only,
    fetcher: props.fetcher,
    adapter: props.adapter,
})

defineExpose({reload, currentQuery: () => ({...query.value})})

const columnSelector = computed(() => table.value.columns.some((column) => column.can_be_hidden))

const hiddenColumns = ref<Set<string>>(new Set(
    props.table.columns.filter(column => column.hidden).map(column => column.key)
))

const visibleColumns = computed(() =>
    table.value.columns.filter(column => !hiddenColumns.value.has(column.key))
)

function toggleColumn(columnKey: string, visible: boolean) {
    if (visible) {
        hiddenColumns.value.delete(columnKey)
    } else {
        hiddenColumns.value.add(columnKey)
    }
    hiddenColumns.value = new Set(hiddenColumns.value)
}

const filterDropdownOpen = ref(false)

function handleFilterChange(key: string, value: string) {
    filterDropdownOpen.value = false
    applyFilter(key, value)
}

const handleTextFilterChange = useDebounceFn(
    (key: string, value: string) => applyFilter(key, value),
    350
)

const searchValue = ref(props.table.searchInputs?.global?.value || '')

const handleSearch = useDebounceFn((value: string) => applySearch(value), 350)

watch(searchValue, (newValue) => {
    handleSearch(newValue)
})

function getCellValue(row: any, key: string) {
    return key.split('.').reduce((obj, k) => obj?.[k], row)
}

function isTruthy(value: any): boolean {
    return value === true || value === 1 || value === '1'
}

const showsPagination = computed(() => Boolean(table.value.pagination && table.value.pagination.last_page > 1))
const showsTopPagination = computed(() => showsPagination.value && props.paginationPosition !== 'bottom')
const showsBottomPagination = computed(() => showsPagination.value && props.paginationPosition !== 'top')

const rowSelection = ref<Set<number>>(new Set())
const allResultsSelected = ref(false)
const selectedCount = computed(() => rowSelection.value.size)
const bulkActions = computed(() => table.value.bulkActions ?? [])
const allVisibleItemsAreSelected = computed(() =>
    allResultsSelected.value ||
    (table.value.data.length > 0 && selectedCount.value > 0)
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
    rowSelection.value = new Set(table.value.data.map((_, index) => index))
}

function selectAllResults() {
    allResultsSelected.value = true
    rowSelection.value = new Set(table.value.data.map((_, index) => index))
}

function resetRowSelection() {
    allResultsSelected.value = false
    rowSelection.value = new Set()
}

function handleRowClick(index: number, e: MouseEvent) {
    if (!table.value.rowLinks || !table.value.rowLinks[index]) return

    const cell = (e.target as HTMLElement).closest('td')
    if (cell) {
        const cellIndex = Array.from(cell.parentElement!.children).indexOf(cell)
        const column = visibleColumns.value[cellIndex]
        if (column && column.clickable === false) return
    }

    const url = table.value.rowLinks[index]

    if (table.value.rowLinkTarget === '_blank') {
        window.open(url, '_blank', 'noopener')

        return
    }

    if (table.value.rowLinkType === 'modal') {
        fetch(url, {headers: {Accept: 'application/json'}})
            .then((r) => r.json())
            .then((data) => window.dispatchEvent(new CustomEvent('table-builder:open-modal', {detail: data})))

        return
    }

    if (table.value.rowLinkType === 'href') {
        window.location.href = url

        return
    }

    router.visit(url)
}

function handlePerPageChange(value: string) {
    applyPerPage(value)
}

function navigatePage(direction: -1 | 1) {
    applyPage(direction)
}

const actionError = ref<string | null>(null)

function performBulkAction(action: any) {
    const ids = Array.from(rowSelection.value).map(index => (table.value.data[index] as any)?.id).filter(Boolean)
    if (ids.length === 0) return

    actionError.value = null

    if (props.transport === 'http') {
        fetch(action.url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', Accept: 'application/json'},
            body: JSON.stringify({ids}),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(t('vue-table-builder::table.bulk_actions.error'))
                }

                resetRowSelection()
                reload()
            })
            .catch((exception) => {
                actionError.value = exception instanceof Error ? exception.message : String(exception)
            })

        return
    }

    router.post(action.url, {ids}, {
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
                <DropdownMenu v-if="table.filters && Object.keys(table.filters).length > 0"
                              v-model:open="filterDropdownOpen">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            <Funnel class="h-4 w-4"/>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64">
                        <div v-for="(filter, index) in table.filters" :key="index" class="p-2">
                            <label class="mb-2 block text-sm font-medium capitalize">{{ filter.label }}</label>

                            <Input
                                v-if="filter.type === 'text'"
                                class="w-full"
                                :model-value="filter.value || ''"
                                :placeholder="filter.label"
                                @update:model-value="(value: string | number) => handleTextFilterChange(filter.key, String(value))"
                            />

                            <select
                                v-else
                                class="w-full rounded-md border bg-white px-3 py-2 dark:bg-gray-800"
                                :value="filter.value || ''"
                                @change="(e) => handleFilterChange(filter.key, (e.target as HTMLSelectElement).value)">
                                <option v-for="(optionLabel, optionValue) in filter.options" :key="optionValue"
                                        :value="optionValue">
                                    {{ optionLabel }}
                                </option>
                            </select>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
                <!-- Search Input -->
                <div v-if="table.searchInputs?.global" class="relative flex-1">
                    <Search class="absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground"/>
                    <Input ref="searchInput" v-model="searchValue" :placeholder="table.searchInputs?.global?.label"
                           class="pl-8"/>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <TablePagination
                    v-if="showsTopPagination"
                    compact
                    :pagination="table.pagination!"
                    :per-page-options="table.perPageOptions"
                    @update:per-page="handlePerPageChange"
                    @navigate="navigatePage"
                />

                <slot name="toolbar" />

                <DropdownMenu v-if="columnSelector">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" class="ml-auto">
                            {{ t('vue-table-builder::table.columns') }}
                            <ChevronDown class="ml-2 h-4 w-4"/>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuCheckboxItem
                            v-for="column in table.columns.filter((column) => column.can_be_hidden)" :key="column.key"
                            class="capitalize" :model-value="! hiddenColumns.has(column.key)"
                            @update:model-value="(checked) => toggleColumn(column.key, checked)">
                            {{ column.label }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div v-if="transportError"
             class="mb-4 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            {{ transportError }}
        </div>

        <div v-if="actionError"
             class="mb-4 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            {{ actionError }}
        </div>

        <!-- Bulk Actions Bar -->
        <div v-if="table.bulkActions && table.bulkActions.length > 0 && selectedCount > 0"
             class="mb-4 flex items-center justify-between rounded-md border bg-gray-50 p-3 dark:bg-gray-800">
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
                            <ChevronDown class="ml-2 h-4 w-4"/>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem v-for="action in bulkActions" :key="action.label"
                                          @click="() => performBulkAction(action)">
                            {{ action.label }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator v-if="bulkActions.length > 0"/>
                        <DropdownMenuItem @click="resetRowSelection">
                            {{ t('vue-table-builder::table.bulk_actions.clear_selection') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div class="rounded-md border" :class="loading ? 'opacity-60 transition-opacity' : ''"
             :aria-busy="loading">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead v-if="bulkActions.length > 0" :class="[table.headClass, 'w-10']">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <div class="cursor-pointer">
                                        <Checkbox :model-value="allVisibleItemsAreSelected"
                                                  class="h-4 w-4 pointer-events-none"/>
                                    </div>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem
                                        @click="selectedCount > 0 ? resetRowSelection() : selectCurrentPage()">
                                        {{
                                            selectedCount > 0 ? t('vue-table-builder::table.bulk_actions.clear_selection') : t('vue-table-builder::table.bulk_actions.select_this_page', {count: String(table.data.length)})
                                        }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="table.pagination" @click="selectAllResults">
                                        {{
                                            t('vue-table-builder::table.bulk_actions.select_all_results', {total: String(table.pagination.total)})
                                        }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableHead>
                        <TableHead v-for="column in visibleColumns" :key="column.key" :class="table.headClass">
                            <button v-if="column.sortable" @click="applySort(column)"
                                    class="flex cursor-pointer items-center gap-1.5 hover:text-foreground"
                                    :class="{ 'font-semibold text-foreground': sortFor(column) }"
                                    type="button">
                                {{ column.label }}
                                <ArrowUp v-if="sortFor(column) === 'asc'" class="h-4 w-4"/>
                                <ArrowDown v-else-if="sortFor(column) === 'desc'" class="h-4 w-4"/>
                                <ArrowUpDown v-else class="h-4 w-4 opacity-40"/>
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </TableHead>
                        <TableHead v-if="$slots.actions" :class="[table.headClass, 'w-px text-right']" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!table.data || table.data.length === 0">
                        <TableCell :colspan="visibleColumns.length + (bulkActions.length > 0 ? 1 : 0) + ($slots.actions ? 1 : 0)" class="text-center text-muted-foreground">
                            {{ t('vue-table-builder::table.no_results') }}
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="(row, index) in table.data" :key="index"
                              @click="table.rowLinks && table.rowLinks[index] ? handleRowClick(index, $event) : undefined"
                              :class="table.rowLinks && table.rowLinks[index] ? 'cursor-pointer hover:bg-muted/50' : ''">
                        <TableCell v-if="bulkActions.length > 0" :class="table.cellClass" @click.stop>
                            <Checkbox :model-value="isRowSelected(index)"
                                      @update:model-value="() => toggleRowSelection(index)" class="h-4 w-4"/>
                        </TableCell>
                        <TableCell v-for="column in visibleColumns" :key="column.key"
                                   :class="[table.cellClass, column.class]">
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="getCellValue(row, column.key)"
                                :index="index"
                                :column="column"
                            >
                                <Check
                                    v-if="column.boolean && isTruthy(getCellValue(row, column.key))"
                                    class="size-4 text-emerald-600"
                                    role="img"
                                    aria-label="true"
                                />
                                <X
                                    v-else-if="column.boolean"
                                    class="size-4 text-muted-foreground"
                                    role="img"
                                    aria-label="false"
                                />
                                <span v-else v-html="getCellValue(row, column.key)"></span>
                            </slot>
                        </TableCell>
                        <TableCell v-if="$slots.actions" :class="[table.cellClass, 'text-right']" @click.stop>
                            <slot name="actions" :row="row" :index="index" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <TablePagination
            v-if="showsBottomPagination"
            class="mt-3"
            :pagination="table.pagination!"
            :per-page-options="table.perPageOptions"
            @update:per-page="handlePerPageChange"
            @navigate="navigatePage"
        />
    </div>
</template>
