<script setup lang="ts">
import {router} from '@inertiajs/vue3'
import {computed, getCurrentInstance, ref, useSlots, watch} from 'vue'
import {Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow,} from './ui/table'
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
import {ArrowDown, ArrowUp, ArrowUpDown, Check, ChevronDown, Download, Funnel, GripVertical, Search, X} from '@lucide/vue'
import type {BulkAction, ConfirmDetails, EmptyState, Filter, RowAction, TableData} from '../types/table-builder'
import {useDebounceFn} from '@vueuse/core'
import {useTranslations} from '../composables/useTranslations'
import {useTablePolling} from '../composables/useTablePolling'
import {cn} from '../lib/utils'
import {assertSupportedSchemaVersion} from '../lib/schema-version'
import {DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle} from 'reka-ui'
import {useTableTransport, xsrfHeaders, type TableAdapter, type TableFetcher, type TableTransport} from '../composables/useTableTransport'
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
    reorderable?: boolean
    rowClass?: (row: any, index: number) => string | string[] | Record<string, boolean> | undefined
    emptyState?: EmptyState
    confirm?: (message: string, details?: ConfirmDetails) => boolean | Promise<boolean>
}>(), {
    transport: 'inertia',
    paginationPosition: 'bottom',
    reorderable: false,
})

assertSupportedSchemaVersion(props.table.schemaVersion)

type RowKey = string | number

const emit = defineEmits<{
    reorder: [{from: number; to: number; row: any}]
    'row-click': [{row: any; index: number; key: RowKey | null; event: MouseEvent}]
}>()

const listensToRowClick = Boolean(getCurrentInstance()?.vnode.props?.onRowClick)

function clickedUnclickableColumn(event: MouseEvent): boolean {
    const columnKey = (event.target as HTMLElement | null)?.closest<HTMLElement>('td[data-column-key]')?.dataset.columnKey

    return visibleColumns.value.some((column) => column.key === columnKey && column.clickable === false)
}

function clickRow(row: any, index: number, event: MouseEvent): void {
    if (clickedUnclickableColumn(event)) {
        return
    }

    if (table.value.rowLinks && table.value.rowLinks[index]) {
        handleRowClick(index)

        return
    }

    emit('row-click', {row, index, key: rowKeyOf(row), event})
}

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
    resetFilters,
    reload,
    refresh,
    stateParams,
} = useTableTransport({
    table: computed(() => props.table),
    transport: computed(() => props.transport),
    source: computed(() => props.source),
    name: tableName,
    only: props.only,
    fetcher: props.fetcher,
    adapter: props.adapter,
})

function exportUrl(): string | null {
    const url = table.value.export?.url

    if (!url) {
        return null
    }

    const state = stateParams().toString()

    return state ? `${url}${url.includes('?') ? '&' : '?'}${state}` : url
}

function prepareExport(event: MouseEvent): void {
    const url = exportUrl()

    if (url) {
        (event.currentTarget as HTMLAnchorElement).href = url
    }
}

const rowKeyName = computed(() => table.value.rowKey || 'id')

function rawRowValue(row: any, key: string): any {
    if (key === rowKeyName.value && row !== null && typeof row === 'object' && '__key' in row) {
        return row.__key
    }

    return row?.[key]
}

function rowKeyOf(row: any): RowKey | null {
    const key = rawRowValue(row, rowKeyName.value)

    return typeof key === 'string' || typeof key === 'number' ? key : null
}

function rowVnodeKey(row: any, index: number): RowKey {
    return rowKeyOf(row) ?? `row-index-${index}`
}

defineExpose({reload, currentQuery: () => ({...query.value}), exportUrl})

const columnSelector = computed(() => table.value.columnToggle !== false && table.value.columns.some((column) => column.can_be_hidden))

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

const slots = useSlots()

function rowActionsFor(index: number): RowAction[] {
    return table.value.rowActions?.[index] ?? []
}

const hasRowActions = computed(() => (table.value.rowActions ?? []).some((actions) => actions.length > 0))
const hasActions = computed(() => Boolean(slots.actions) || hasRowActions.value)

const actionRunning = ref(false)

const awaitingConfirmation = ref(false)

async function confirmed(message: string | null, details?: ConfirmDetails): Promise<boolean> {
    if (!message) {
        return true
    }

    awaitingConfirmation.value = true

    try {
        if (props.confirm) {
            return details ? await props.confirm(message, details) : await props.confirm(message)
        }

        return window.confirm(details?.text ? `${message}\n\n${details.text}` : message)
    } finally {
        awaitingConfirmation.value = false
    }
}

function opensInNewTab(event: MouseEvent): boolean {
    return event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1
}

function downloadName(action: RowAction): string {
    return typeof action.download === 'string' ? action.download : ''
}

function triggerDownload(action: RowAction): void {
    const link = document.createElement('a')

    link.href = action.url
    link.download = downloadName(action)
    link.rel = 'noopener'
    link.style.display = 'none'

    document.body.appendChild(link)
    link.click()
    link.remove()
}

async function runRowAction(action: RowAction, event?: MouseEvent): Promise<void> {
    if (action.disabled || actionRunning.value) {
        event?.preventDefault()

        return
    }

    if (action.download) {
        if (event && !action.confirm) {
            return
        }

        event?.preventDefault()

        if (await confirmed(action.confirm)) {
            triggerDownload(action)
        }

        return
    }

    if (action.method === 'get' && event && opensInNewTab(event)) {
        return
    }

    event?.preventDefault()

    if (!(await confirmed(action.confirm))) {
        return
    }

    actionError.value = null

    if (action.method === 'get') {
        router.visit(action.url)

        return
    }

    if (props.transport === 'http') {
        actionRunning.value = true

        fetch(action.url, {
            method: action.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {Accept: 'application/json', ...xsrfHeaders()},
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(t('vue-table-builder::table.row_actions.error'))
                }

                reload()
            })
            .catch((exception) => {
                actionError.value = exception instanceof Error ? exception.message : String(exception)
            })
            .finally(() => {
                actionRunning.value = false
            })

        return
    }

    router.visit(action.url, {
        method: action.method,
        preserveScroll: true,
        onStart: () => {
            actionRunning.value = true
        },
        onFinish: () => {
            actionRunning.value = false
        },
        onError: (errors) => {
            actionError.value = Object.values(errors)[0] ?? t('vue-table-builder::table.row_actions.error')
        },
    })
}

const isFiltered = computed(() =>
    (table.value.filters ?? []).some((filter) => Boolean(filter.value) && filter.value !== '..')
    || Object.values(table.value.searchInputs ?? {}).some((searchInput) => Boolean(searchInput?.value))
)

const resolvedEmptyState = computed<EmptyState>(() => ({...(table.value.emptyState ?? {}), ...(props.emptyState ?? {})}))

const emptyTitle = computed(() => {
    const state = resolvedEmptyState.value

    if (isFiltered.value) {
        return state.filteredTitle || state.title || t('vue-table-builder::table.no_results')
    }

    return state.title || t('vue-table-builder::table.no_results')
})

const emptyDescription = computed(() => {
    const state = resolvedEmptyState.value

    return (isFiltered.value ? state.filteredDescription : state.description) || null
})

const filterDropdownOpen = ref(false)

const actionError = ref<string | null>(null)

const sortedByAnotherColumn = computed(() =>
    table.value.columns.some((column) => sortFor(column) && column.key !== table.value.reorder?.column)
)

const showsReorderHandles = computed(() => {
    if (table.value.reorder) {
        return !sortedByAnotherColumn.value
    }

    return props.reorderable
})

const draggedIndex = ref<number | null>(null)
const overIndex = ref<number | null>(null)

const rowColspan = computed(() =>
    visibleColumns.value.length
    + (bulkActions.value.length > 0 ? 1 : 0)
    + (hasActions.value ? 1 : 0)
    + (showsReorderHandles.value ? 1 : 0)
)

function startRowDrag(index: number, event: DragEvent): void {
    draggedIndex.value = index

    if (!event.dataTransfer) {
        return
    }

    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(index))

    const row = (event.target as HTMLElement | null)?.closest('tr')

    if (row) {
        event.dataTransfer.setDragImage(row, 0, 0)
    }
}

function dragOverRow(index: number, event: DragEvent): void {
    if (draggedIndex.value === null) {
        return
    }

    event.preventDefault()
    overIndex.value = index
}

function dropOnRow(index: number, event: DragEvent): void {
    event.preventDefault()

    const from = draggedIndex.value

    endRowDrag()

    if (from === null || from === index) {
        return
    }

    emit('reorder', {from, to: index, row: table.value.data[from]})

    persistReorder(from, index)
}

function persistReorder(from: number, to: number): void {
    const config = table.value.reorder

    if (!config?.url) {
        return
    }

    const key = config.key || rowKeyName.value
    const payload = {
        id: rawRowValue(table.value.data[from], key),
        to: rawRowValue(table.value.data[to], key),
    }

    actionError.value = null

    if (props.transport === 'http') {
        fetch(config.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', Accept: 'application/json', ...xsrfHeaders()},
            body: JSON.stringify(payload),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(t('vue-table-builder::table.reorder.error'))
                }

                reload()
            })
            .catch((exception) => {
                actionError.value = exception instanceof Error ? exception.message : String(exception)
            })

        return
    }

    router.post(config.url, payload, {
        preserveScroll: true,
        preserveState: true,
        ...(props.only ? {only: props.only} : {}),
        onError: (errors) => {
            actionError.value = Object.values(errors)[0] ?? t('vue-table-builder::table.reorder.error')
        },
    })
}

function endRowDrag(): void {
    draggedIndex.value = null
    overIndex.value = null
}

function handleFilterChange(key: string, value: string) {
    filterDropdownOpen.value = false
    applyFilter(key, value)
}

const textFilterValues = ref<Record<string, string>>(
    Object.fromEntries(
        (props.table.filters ?? [])
            .filter(filter => filter.type === 'text')
            .map(filter => [filter.key, filter.value ?? ''])
    )
)

const handleTextFilterChange = useDebounceFn(
    (key: string, value: string) => applyFilter(key, value),
    350
)

function splitDateRange(value: string | null | undefined): {from: string; to: string} {
    const text = String(value ?? '')

    if (!text.includes('..')) {
        return {from: '', to: ''}
    }

    const [from = '', to = ''] = text.split('..')

    return {from, to}
}

const dateRangeValues = ref<Record<string, {from: string; to: string}>>(
    Object.fromEntries(
        (props.table.filters ?? [])
            .filter(filter => filter.type === 'date_range')
            .map(filter => [filter.key, splitDateRange(filter.value)])
    )
)

const customDateRanges = ref<Set<string>>(new Set())

function presetSelection(filter: Filter): string {
    const value = filter.value ?? ''

    if ((filter.presets ?? []).some((preset) => preset.key === value)) {
        return value
    }

    const range = splitDateRange(value)

    if (customDateRanges.value.has(filter.key) || range.from || range.to) {
        return 'custom'
    }

    return '..'
}

function showsDateRangeInputs(filter: Filter): boolean {
    return !(filter.presets ?? []).length || presetSelection(filter) === 'custom'
}

function handlePresetChange(filter: Filter, value: string) {
    if (value === 'custom') {
        customDateRanges.value = new Set([...customDateRanges.value, filter.key])

        return
    }

    customDateRanges.value = new Set([...customDateRanges.value].filter((key) => key !== filter.key))
    dateRangeValues.value[filter.key] = {from: '', to: ''}

    handleFilterChange(filter.key, value)
}

function handleDateRangeInput(filter: Filter, part: 'from' | 'to', value: string) {
    const current = dateRangeValues.value[filter.key] ?? {from: '', to: ''}
    const next = {...current, [part]: value}

    dateRangeValues.value[filter.key] = next

    if (next.from || next.to) {
        applyFilter(filter.key, `${next.from}..${next.to}`)

        return
    }

    applyFilter(filter.key, (filter.presets ?? []).length ? '..' : '')
}

function handleTextFilterInput(key: string, value: string) {
    textFilterValues.value[key] = value

    handleTextFilterChange(key, value)
}

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

const showsTotals = computed(() => Boolean(table.value.totals) && (table.value.data ?? []).length > 0)

function totalFor(key: string): string | number | null {
    return table.value.totals?.values?.[key] ?? null
}

function hasTotal(key: string): boolean {
    return totalFor(key) !== null
}

const showsPagination = computed(() => Boolean(table.value.pagination && table.value.pagination.last_page > 1))
const showsTopPagination = computed(() => showsPagination.value && props.paginationPosition !== 'bottom')
const showsBottomPagination = computed(() => showsPagination.value && props.paginationPosition !== 'top')

const selectedKeys = ref<Set<RowKey>>(new Set())
const allResultsSelected = ref(false)
const selectedCount = computed(() =>
    allResultsSelected.value && table.value.pagination ? table.value.pagination.total : selectedKeys.value.size
)
const bulkActions = computed(() => table.value.bulkActions ?? [])

const pageKeys = computed(() =>
    (table.value.data ?? []).map((row) => rowKeyOf(row)).filter((key): key is RowKey => key !== null)
)

const allVisibleItemsAreSelected = computed(() =>
    allResultsSelected.value ||
    (pageKeys.value.length > 0 && pageKeys.value.every((key) => selectedKeys.value.has(key)))
)

function isRowSelected(row: any): boolean {
    if (allResultsSelected.value) {
        return true
    }

    const key = rowKeyOf(row)

    return key !== null && selectedKeys.value.has(key)
}

function toggleRowSelection(row: any) {
    const key = rowKeyOf(row)

    if (key === null) {
        return
    }

    const next = new Set(allResultsSelected.value ? pageKeys.value : selectedKeys.value)

    allResultsSelected.value = false

    if (next.has(key)) {
        next.delete(key)
    } else {
        next.add(key)
    }

    selectedKeys.value = next
}

function selectCurrentPage() {
    allResultsSelected.value = false
    selectedKeys.value = new Set([...selectedKeys.value, ...pageKeys.value])
}

function selectAllResults() {
    allResultsSelected.value = true
    selectedKeys.value = new Set(pageKeys.value)
}

function resetRowSelection() {
    allResultsSelected.value = false
    selectedKeys.value = new Set()
}

const filterState = computed(() => JSON.stringify({
    filters: (table.value.filters ?? []).map((filter) => [filter.key, filter.value || null]),
    search: Object.entries(table.value.searchInputs ?? {}).map(([key, input]) => [key, input?.value || null]),
}))

watch(filterState, () => resetRowSelection())

function handleRowClick(index: number) {
    if (!table.value.rowLinks || !table.value.rowLinks[index]) return

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

async function failureMessage(response: Response, fallback: string): Promise<string> {
    const body = await response.json().catch(() => null)
    const firstError = body?.errors ? Object.values(body.errors).flat()[0] : null

    if (typeof firstError === 'string' && firstError) {
        return firstError
    }

    return typeof body?.message === 'string' && body.message ? body.message : fallback
}

function bulkConfirmation(action: BulkAction): string | null {
    if (!action.confirm) {
        return null
    }

    return typeof action.confirm === 'string' ? action.confirm : t('vue-table-builder::table.bulk_actions.confirm')
}

type BulkPayload = {ids: (string | number)[], state?: string, choice?: string}

function bulkPayload(): BulkPayload | null {
    if (allResultsSelected.value) {
        return {ids: ['*'], state: stateParams().toString()}
    }

    const ids = Array.from(selectedKeys.value)

    return ids.length > 0 ? {ids} : null
}

const choiceAction = ref<BulkAction | null>(null)
const chosenValue = ref('')

const choiceDialogOpen = computed({
    get: () => choiceAction.value !== null,
    set: (open: boolean) => {
        if (!open) {
            choiceAction.value = null
        }
    },
})

function choiceDialogTitle(action: BulkAction): string {
    return typeof action.confirm === 'string' && action.confirm ? action.confirm : action.label
}

function submitChoice(): void {
    const action = choiceAction.value
    const payload = bulkPayload()

    if (!action || !payload || !chosenValue.value) {
        return
    }

    choiceAction.value = null

    postBulkAction(action, {...payload, choice: chosenValue.value})
}

async function performBulkAction(action: BulkAction) {
    const payload = bulkPayload()

    if (!payload) {
        return
    }

    if (action.choices?.length) {
        chosenValue.value = ''
        choiceAction.value = action

        return
    }

    const confirmation = bulkConfirmation(action)

    if (confirmation && !(await confirmed(confirmation, {
        text: action.confirmText || null,
        confirmButton: action.confirmButton || null,
        cancelButton: action.cancelButton || null,
    }))) {
        return
    }

    postBulkAction(action, payload)
}

function postBulkAction(action: BulkAction, payload: BulkPayload): void {
    actionError.value = null

    if (props.transport === 'http') {
        fetch(action.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', Accept: 'application/json', ...xsrfHeaders()},
            body: JSON.stringify(payload),
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(await failureMessage(response, t('vue-table-builder::table.bulk_actions.error')))
                }

                resetRowSelection()
                reload()
            })
            .catch((exception) => {
                actionError.value = exception instanceof Error ? exception.message : String(exception)
            })

        return
    }

    router.post(action.url, payload, {
        preserveScroll: true,
        onSuccess: () => resetRowSelection(),
        onError: (errors) => {
            actionError.value = Object.values(errors)[0] ?? t('vue-table-builder::table.bulk_actions.error')
        },
    })
}

useTablePolling({
    seconds: computed(() => table.value.poll),
    paused: computed(() =>
        selectedCount.value > 0
        || choiceAction.value !== null
        || awaitingConfirmation.value
        || actionRunning.value
        || loading.value
        || draggedIndex.value !== null
    ),
    refresh,
})
</script>

<template>
    <div>
        <div class="flex items-center gap-4 pt-2 pb-4">
            <div class="flex flex-1 items-center gap-2">
                <!-- Filter dropdown -->
                <DropdownMenu v-if="table.filters && Object.keys(table.filters).length > 0"
                              v-model:open="filterDropdownOpen">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" :aria-label="t('vue-table-builder::table.filters.toggle')" data-filter-toggle>
                            <Funnel class="h-4 w-4" aria-hidden="true"/>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64">
                        <div v-for="(filter, index) in table.filters" :key="index" class="p-2">
                            <label class="mb-2 block text-sm font-medium capitalize">{{ filter.label }}</label>

                            <Input
                                v-if="filter.type === 'text'"
                                class="w-full"
                                :model-value="textFilterValues[filter.key] ?? ''"
                                :placeholder="filter.label"
                                @update:model-value="(value: string | number) => handleTextFilterInput(filter.key, String(value))"
                            />

                            <Input
                                v-else-if="filter.type === 'date'"
                                type="date"
                                class="w-full"
                                :model-value="filter.value ?? ''"
                                :data-filter-key="filter.key"
                                @change="(e: Event) => handleFilterChange(filter.key, (e.target as HTMLInputElement).value)"
                            />

                            <div v-else-if="filter.type === 'date_range'" class="flex flex-col gap-2">
                                <select
                                    v-if="filter.presets?.length"
                                    class="w-full rounded-md border bg-white px-3 py-2 dark:bg-gray-800"
                                    :value="presetSelection(filter)"
                                    :data-filter-preset="filter.key"
                                    @change="(e) => handlePresetChange(filter, (e.target as HTMLSelectElement).value)">
                                    <option value="..">{{ t('vue-table-builder::table.filters.any') }}</option>
                                    <option v-for="preset in filter.presets" :key="preset.key" :value="preset.key">
                                        {{ preset.label }}
                                    </option>
                                    <option value="custom">{{ t('vue-table-builder::table.filters.custom') }}</option>
                                </select>
                                <template v-if="showsDateRangeInputs(filter)">
                                    <Input
                                        type="date"
                                        class="w-full"
                                        :aria-label="`${filter.label} ${t('vue-table-builder::table.filters.from')}`"
                                        :model-value="dateRangeValues[filter.key]?.from ?? ''"
                                        :data-filter-key="`${filter.key}.from`"
                                        @change="(e: Event) => handleDateRangeInput(filter, 'from', (e.target as HTMLInputElement).value)"
                                    />
                                    <Input
                                        type="date"
                                        class="w-full"
                                        :aria-label="`${filter.label} ${t('vue-table-builder::table.filters.to')}`"
                                        :model-value="dateRangeValues[filter.key]?.to ?? ''"
                                        :data-filter-key="`${filter.key}.to`"
                                        @change="(e: Event) => handleDateRangeInput(filter, 'to', (e.target as HTMLInputElement).value)"
                                    />
                                </template>
                            </div>

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

                <Button v-if="table.export?.url" variant="outline" as-child>
                    <a :href="table.export.url" download data-table-export @click="prepareExport">
                        <Download class="h-4 w-4"/>
                        {{ table.export.label }}
                    </a>
                </Button>

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
                        <TableHead v-if="showsReorderHandles" :class="[table.headClass, 'w-px']" />
                        <TableHead v-if="bulkActions.length > 0" :class="[table.headClass, 'w-10']">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <div class="cursor-pointer">
                                        <Checkbox :model-value="allVisibleItemsAreSelected"
                                                  class="h-4 w-4 pointer-events-none"/>
                                    </div>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem v-if="selectedCount > 0" @click="resetRowSelection">
                                        {{ t('vue-table-builder::table.bulk_actions.clear_selection') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="!allVisibleItemsAreSelected" @click="selectCurrentPage">
                                        {{ t('vue-table-builder::table.bulk_actions.select_this_page', {count: String(table.data.length)}) }}
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
                        <TableHead v-if="hasActions" :class="[table.headClass, 'w-px text-right']" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!table.data || table.data.length === 0">
                        <TableCell :colspan="rowColspan" class="text-center text-muted-foreground" data-empty-state>
                            <slot
                                name="empty"
                                :filtered="isFiltered"
                                :title="emptyTitle"
                                :description="emptyDescription"
                                :reset="resetFilters"
                            >
                                <p :class="emptyDescription ? 'font-medium text-foreground' : ''">{{ emptyTitle }}</p>
                                <p v-if="emptyDescription" class="mt-1 text-sm">{{ emptyDescription }}</p>
                            </slot>
                        </TableCell>
                    </TableRow>
                    <template v-for="(row, index) in table.data" :key="rowVnodeKey(row, index)">
                        <TableRow
                            @click="clickRow(row, index, $event)"
                            @dragover="showsReorderHandles ? dragOverRow(index, $event) : undefined"
                            @drop="showsReorderHandles ? dropOnRow(index, $event) : undefined"
                            :class="[
                                (table.rowLinks && table.rowLinks[index]) || listensToRowClick ? 'cursor-pointer hover:bg-muted/50' : '',
                                cn(row?.__class, rowClass ? rowClass(row, index) : undefined),
                                draggedIndex === index ? 'opacity-50' : '',
                                overIndex === index && draggedIndex !== index ? 'border-t-2 border-t-primary' : '',
                            ]">
                            <TableCell v-if="showsReorderHandles" :class="[table.cellClass, 'w-px']" @click.stop>
                                <span
                                    draggable="true"
                                    class="inline-flex cursor-grab text-muted-foreground hover:text-foreground"
                                    data-reorder-handle
                                    @dragstart="startRowDrag(index, $event)"
                                    @dragend="endRowDrag"
                                >
                                    <GripVertical class="size-4"/>
                                </span>
                            </TableCell>
                            <TableCell v-if="bulkActions.length > 0" :class="table.cellClass" @click.stop>
                                <Checkbox :model-value="isRowSelected(row)"
                                          :disabled="rowKeyOf(row) === null"
                                          @update:model-value="() => toggleRowSelection(row)" class="h-4 w-4"/>
                            </TableCell>
                            <TableCell v-for="column in visibleColumns" :key="column.key" :data-column-key="column.key"
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
                                        :aria-label="t('export.yes')"
                                    />
                                    <X
                                        v-else-if="column.boolean"
                                        class="size-4 text-muted-foreground"
                                        role="img"
                                        :aria-label="t('export.no')"
                                    />
                                    <span v-else v-html="getCellValue(row, column.key)"></span>
                                </slot>
                            </TableCell>
                            <TableCell v-if="hasActions" :class="[table.cellClass, 'text-right']" @click.stop>
                                <slot name="actions" :row="row" :index="index" :actions="rowActionsFor(index)" :run="runRowAction">
                                    <div class="flex justify-end gap-2">
                                        <template v-for="action in rowActionsFor(index)" :key="action.key">
                                            <span v-if="action.disabled" :title="action.disabledReason ?? undefined" class="inline-flex">
                                                <Button size="sm" :variant="action.variant" disabled :data-row-action="action.key">
                                                    {{ action.label }}
                                                </Button>
                                            </span>
                                            <Button v-else-if="action.download" size="sm" :variant="action.variant" as-child>
                                                <a
                                                    :href="action.url"
                                                    :download="downloadName(action)"
                                                    :data-row-action="action.key"
                                                    data-row-action-download
                                                    @click="runRowAction(action, $event)"
                                                >
                                                    {{ action.label }}
                                                </a>
                                            </Button>
                                            <Button v-else-if="action.method === 'get'" size="sm" :variant="action.variant" as-child>
                                                <a :href="action.url" :data-row-action="action.key" @click="runRowAction(action, $event)">
                                                    {{ action.label }}
                                                </a>
                                            </Button>
                                            <Button
                                                v-else
                                                size="sm"
                                                :variant="action.variant"
                                                :disabled="actionRunning"
                                                :data-row-action="action.key"
                                                @click="runRowAction(action, $event)"
                                            >
                                                {{ action.label }}
                                            </Button>
                                        </template>
                                    </div>
                                </slot>
                            </TableCell>
                        </TableRow>
                        <slot name="row-after" :row="row" :index="index" :colspan="rowColspan" :columns="visibleColumns" />
                    </template>
                </TableBody>
                <TableFooter v-if="showsTotals" data-totals>
                    <TableRow>
                        <TableCell v-if="showsReorderHandles" :class="[table.cellClass, 'w-px']" />
                        <TableCell v-if="bulkActions.length > 0" :class="table.cellClass" />
                        <TableCell v-for="(column, columnIndex) in visibleColumns" :key="column.key"
                                   :data-total-key="column.key" :class="[table.cellClass, column.class]">
                            <slot
                                :name="`total-${column.key}`"
                                :value="totalFor(column.key)"
                                :column="column"
                                :totals="table.totals"
                            >
                                <span v-if="hasTotal(column.key)" v-html="totalFor(column.key)"></span>
                                <span v-else-if="columnIndex === 0 && table.totals?.label">{{ table.totals.label }}</span>
                            </slot>
                        </TableCell>
                        <TableCell v-if="hasActions" :class="table.cellClass" />
                    </TableRow>
                </TableFooter>
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
        <DialogRoot v-model:open="choiceDialogOpen">
            <DialogPortal>
                <DialogOverlay class="fixed inset-0 z-50 bg-black/50"/>
                <DialogContent
                    v-if="choiceAction"
                    data-bulk-choice-dialog
                    class="fixed top-1/2 left-1/2 z-50 grid w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg"
                >
                    <form class="grid gap-4" @submit.prevent="submitChoice">
                        <div class="grid gap-1.5">
                            <DialogTitle class="text-lg font-semibold">{{ choiceDialogTitle(choiceAction) }}</DialogTitle>
                            <DialogDescription v-if="choiceAction.confirmText" class="text-sm text-muted-foreground">
                                {{ choiceAction.confirmText }}
                            </DialogDescription>
                        </div>
                        <label class="grid gap-1.5 text-sm font-medium">
                            <span>{{ choiceAction.choicesLabel || choiceAction.label }}</span>
                            <select
                                v-model="chosenValue"
                                name="choice"
                                required
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            >
                                <option value="" disabled>{{ t('vue-table-builder::table.bulk_actions.choose') }}</option>
                                <option v-for="choice in choiceAction.choices" :key="choice.value" :value="choice.value">
                                    {{ choice.label }}
                                </option>
                            </select>
                        </label>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="choiceDialogOpen = false">
                                {{ choiceAction.cancelButton || t('vue-table-builder::table.bulk_actions.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="!chosenValue">
                                {{ choiceAction.confirmButton || t('vue-table-builder::table.bulk_actions.apply') }}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </div>
</template>
