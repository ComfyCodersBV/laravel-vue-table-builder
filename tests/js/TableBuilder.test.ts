import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest'
import {router} from '@inertiajs/vue3'
import {mount} from '@vue/test-utils'
import {h} from 'vue'
import TableBuilder from '../../resources/js/components/TableBuilder.vue'
import type {Column, RowAction, TableData} from '../../resources/js/types/table-builder'

const page = vi.hoisted(() => ({props: {} as Record<string, unknown>}))

vi.mock('@inertiajs/vue3', () => ({
    router: {get: vi.fn(), reload: vi.fn(), post: vi.fn(), visit: vi.fn()},
    usePage: () => page,
}))

function column(key: string): Column {
    return {
        key,
        label: key,
        can_be_hidden: false,
        hidden: false,
        sortable: false,
        sorted: false,
        highlight: false,
        class: '',
        alignment: '',
        clickable: true,
        boolean: false,
    }
}

function tableData(): TableData {
    return {
        name: 'lines',
        data: [{id: 'a', name: 'First'}, {id: 'b', name: 'Second'}, {id: 'c', name: 'Third'}],
        columns: [column('name')],
        filters: [],
        searchInputs: {},
        perPageOptions: [],
        defaultSort: '',
        bulkActions: [],
        rowLinks: [],
        rowLinkType: '',
        rowLinkTarget: '_self',
    } as unknown as TableData
}

function dataTransfer() {
    return {effectAllowed: '', setData: vi.fn(), setDragImage: vi.fn()}
}

describe('TableBuilder rows', () => {
    it('emits the source and target position when a row is dropped on another', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData(), reorderable: true}})

        const handles = wrapper.findAll('[data-reorder-handle]')
        const rows = wrapper.findAll('tbody tr')

        await handles[0].trigger('dragstart', {dataTransfer: dataTransfer()})
        await rows[2].trigger('dragover')
        await rows[2].trigger('drop')

        expect(wrapper.emitted('reorder')).toEqual([[{from: 0, to: 2, row: {id: 'a', name: 'First'}}]])
    })

    it('does not emit when a row is dropped on itself', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData(), reorderable: true}})

        await wrapper.findAll('[data-reorder-handle]')[1].trigger('dragstart', {dataTransfer: dataTransfer()})
        await wrapper.findAll('tbody tr')[1].trigger('drop')

        expect(wrapper.emitted('reorder')).toBeUndefined()
    })

    it('renders no handles unless the table is reorderable', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData()}})

        expect(wrapper.findAll('[data-reorder-handle]')).toHaveLength(0)
    })

    it('renders the row-after slot under its row with the full row width', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData(), reorderable: true},
            slots: {
                actions: () => h('button', 'edit'),
                'row-after': ({row, colspan}: {row: any; colspan: number}) =>
                    h('tr', {class: 'nested'}, [h('td', {colspan}, `options of ${row.name}`)]),
            },
        })

        const rows = wrapper.findAll('tbody tr')

        expect(rows).toHaveLength(6)
        expect(rows[1].classes()).toContain('nested')
        expect(rows[1].text()).toBe('options of First')
        expect(rows[1].find('td').attributes('colspan')).toBe('3')
    })

    it('adds the classes returned by rowClass', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData(), rowClass: (row: any) => `status-${row.id}`},
        })

        expect(wrapper.findAll('tbody tr')[1].classes()).toContain('status-b')
    })

    it('emits row-click with the clicked row but not for clicks in the actions cell', async () => {
        const onRowClick = vi.fn()
        const wrapper = mount(TableBuilder, {
            props: {table: tableData(), onRowClick},
            slots: {actions: () => h('button', {class: 'action'}, 'edit')},
        })

        await wrapper.findAll('tbody tr')[1].find('td').trigger('click')
        await wrapper.findAll('.action')[0].trigger('click')

        expect(onRowClick).toHaveBeenCalledTimes(1)
        expect(onRowClick.mock.calls[0][0]).toMatchObject({row: {id: 'b', name: 'Second'}, index: 1})
        expect(wrapper.findAll('tbody tr')[1].classes()).toContain('cursor-pointer')
    })

    it('ignores clicks in an unclickable column behind the drag handle', async () => {
        const onRowClick = vi.fn()
        const table = tableData()
        table.columns = [column('name'), {...column('status'), clickable: false}]

        const wrapper = mount(TableBuilder, {props: {table, reorderable: true, onRowClick}})

        await wrapper.find('tbody tr [data-column-key="status"]').trigger('click')
        expect(onRowClick).not.toHaveBeenCalled()

        await wrapper.find('tbody tr [data-column-key="name"]').trigger('click')
        expect(onRowClick).toHaveBeenCalledTimes(1)
    })
})

function rowAction(overrides: Partial<RowAction> = {}): RowAction {
    return {
        key: 'edit',
        label: 'Edit',
        url: '/lines/a/edit',
        method: 'get',
        confirm: null,
        variant: 'outline',
        disabled: false,
        disabledReason: null,
        ...overrides,
    }
}

function flush(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('TableBuilder row actions', () => {
    beforeEach(() => {
        vi.mocked(router.visit).mockClear()
    })

    function tableWithActions(): TableData {
        const table = tableData()

        table.rowActions = [
            [rowAction(), rowAction({key: 'delete', label: 'Delete', url: '/lines/a', method: 'delete', variant: 'destructive', confirm: 'Delete this line?'})],
            [rowAction({url: '/lines/b/edit'}), rowAction({key: 'delete', label: 'Delete', url: '/lines/b', method: 'delete', disabled: true, disabledReason: 'Line is invoiced'})],
            [],
        ]

        return table
    }

    it('renders the server-side actions without an actions slot', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableWithActions()}})

        const rows = wrapper.findAll('tbody tr')

        expect(rows[0].findAll('[data-row-action]').map((action) => action.text())).toEqual(['Edit', 'Delete'])
        expect(rows[0].find('a[data-row-action="edit"]').attributes('href')).toBe('/lines/a/edit')
        expect(rows[2].findAll('[data-row-action]')).toHaveLength(0)
    })

    it('renders a disabled action with its reason', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableWithActions()}})

        const disabled = wrapper.findAll('tbody tr')[1].find('[data-row-action="delete"]')

        expect(disabled.attributes('disabled')).toBeDefined()
        expect(disabled.element.parentElement?.getAttribute('title')).toBe('Line is invoiced')
    })

    it('visits a get action through the router', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableWithActions()}})

        await wrapper.find('a[data-row-action="edit"]').trigger('click')
        await flush()

        expect(router.visit).toHaveBeenCalledWith('/lines/a/edit')
    })

    it('asks for confirmation before running a delete action', async () => {
        const confirm = vi.fn(() => true)
        const wrapper = mount(TableBuilder, {props: {table: tableWithActions(), confirm}})

        await wrapper.find('button[data-row-action="delete"]').trigger('click')
        await flush()

        expect(confirm).toHaveBeenCalledWith('Delete this line?')
        expect(router.visit).toHaveBeenCalledWith('/lines/a', expect.objectContaining({method: 'delete'}))
    })

    it('asks each row the confirm text resolved for that row', async () => {
        const confirm = vi.fn(() => true)
        const table = tableData()

        table.rowActions = [
            [rowAction({key: 'disconnect', label: 'Disconnect', url: '/apps/claude', method: 'delete', confirm: 'Disconnect Claude?'})],
            [rowAction({key: 'disconnect', label: 'Disconnect', url: '/apps/chatgpt', method: 'delete', confirm: 'Disconnect ChatGPT?'})],
            [rowAction({key: 'disconnect', label: 'Disconnect', url: '/apps/local', method: 'delete'})],
        ]

        const wrapper = mount(TableBuilder, {props: {table, confirm}})
        const rows = wrapper.findAll('tbody tr')

        await rows[1].find('button[data-row-action="disconnect"]').trigger('click')
        await flush()

        expect(confirm).toHaveBeenCalledWith('Disconnect ChatGPT?')

        await rows[2].find('button[data-row-action="disconnect"]').trigger('click')
        await flush()

        expect(confirm).toHaveBeenCalledTimes(1)
        expect(router.visit).toHaveBeenLastCalledWith('/apps/local', expect.objectContaining({method: 'delete'}))
    })

    it('does not run an action that is not confirmed', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableWithActions(), confirm: () => false}})

        await wrapper.find('button[data-row-action="delete"]').trigger('click')
        await flush()

        expect(router.visit).not.toHaveBeenCalled()
    })

    it('renders a download action as a plain link the browser follows itself', async () => {
        const table = tableData()
        table.rowActions = [[rowAction({key: 'pdf', label: 'PDF', url: '/lines/a/pdf', download: 'line-a.pdf'})], [], []]

        const wrapper = mount(TableBuilder, {props: {table}})
        const link = wrapper.find('a[data-row-action="pdf"]')
        let preventedByComponent: boolean | null = null

        link.element.addEventListener('click', (event) => {
            preventedByComponent = event.defaultPrevented
            event.preventDefault()
        })

        expect(link.attributes('href')).toBe('/lines/a/pdf')
        expect(link.attributes('download')).toBe('line-a.pdf')

        await link.trigger('click')
        await flush()

        expect(preventedByComponent).toBe(false)
        expect(router.visit).not.toHaveBeenCalled()
    })

    it('downloads through a temporary link once a download action is confirmed', async () => {
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
        const table = tableData()
        table.rowActions = [[rowAction({key: 'pdf', label: 'PDF', url: '/lines/a/pdf', download: true, confirm: 'Download?'})], [], []]

        const wrapper = mount(TableBuilder, {props: {table, confirm: () => true}})

        await wrapper.find('a[data-row-action="pdf"]').trigger('click')
        await flush()

        expect(click).toHaveBeenCalledTimes(1)
        expect((click.mock.contexts[0] as HTMLAnchorElement).getAttribute('href')).toBe('/lines/a/pdf')
        expect(router.visit).not.toHaveBeenCalled()

        click.mockRestore()
    })

    it('lets the actions slot override the rendering and receive the actions', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableWithActions()},
            slots: {
                actions: ({actions}: {actions: RowAction[]}) => h('span', {class: 'custom'}, actions.map((action) => action.key).join(',')),
            },
        })

        expect(wrapper.findAll('[data-row-action]')).toHaveLength(0)
        expect(wrapper.findAll('.custom').map((cell) => cell.text())).toEqual(['edit,delete', 'edit,delete', ''])
    })
})

describe('TableBuilder empty state', () => {
    function emptyTable(overrides: Partial<TableData> = {}): TableData {
        return {...tableData(), data: [], ...overrides}
    }

    it('shows the generic text without configuration', () => {
        const wrapper = mount(TableBuilder, {props: {table: emptyTable()}})

        expect(wrapper.find('[data-empty-state]').text()).toBe('no_results')
    })

    it('shows the configured title and description', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: emptyTable({emptyState: {title: 'No lines yet', description: 'Add the first line.'}})},
        })

        expect(wrapper.find('[data-empty-state]').text()).toBe('No lines yetAdd the first line.')
    })

    it('shows the filtered texts while a filter is active', () => {
        const wrapper = mount(TableBuilder, {
            props: {
                table: emptyTable({
                    filters: [{key: 'status', label: 'Status', options: {}, value: 'open', type: 'select'}],
                    emptyState: {title: 'No lines yet', filteredTitle: 'No lines match'},
                }),
            },
        })

        expect(wrapper.find('[data-empty-state]').text()).toBe('No lines match')
    })

    it('lets the prop override the server texts', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: emptyTable({emptyState: {title: 'Server'}}), emptyState: {title: 'Prop'}},
        })

        expect(wrapper.find('[data-empty-state]').text()).toBe('Prop')
    })

    it('renders the empty slot with the filtered state', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: emptyTable({searchInputs: {global: {key: 'global', label: 'Search', value: 'zz'}}})},
            slots: {empty: ({filtered}: {filtered: boolean}) => h('em', filtered ? 'filtered' : 'empty')},
        })

        expect(wrapper.find('[data-empty-state] em').text()).toBe('filtered')
    })
})

describe('TableBuilder reorder persistence', () => {
    beforeEach(() => {
        vi.mocked(router.post).mockClear()
    })

    function persistedTable(): TableData {
        return {...tableData(), reorder: {column: 'sort_order', key: 'id', url: '/table/reorder?signature=x'}}
    }

    it('shows handles and posts the moved row and its target', async () => {
        const wrapper = mount(TableBuilder, {props: {table: persistedTable()}})

        await wrapper.findAll('[data-reorder-handle]')[0].trigger('dragstart', {dataTransfer: dataTransfer()})
        await wrapper.findAll('tbody tr')[2].trigger('drop')

        expect(wrapper.emitted('reorder')).toHaveLength(1)
        expect(router.post).toHaveBeenCalledWith('/table/reorder?signature=x', {id: 'a', to: 'c'}, expect.objectContaining({preserveScroll: true}))
    })

    it('hides the handles while sorted on another column', () => {
        const table = persistedTable()
        table.columns = [{...column('name'), sortable: true, sorted: 'asc'}]

        const wrapper = mount(TableBuilder, {props: {table}})

        expect(wrapper.findAll('[data-reorder-handle]')).toHaveLength(0)
    })

    it('does not post without a reorder url', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData(), reorderable: true}})

        await wrapper.findAll('[data-reorder-handle]')[0].trigger('dragstart', {dataTransfer: dataTransfer()})
        await wrapper.findAll('tbody tr')[2].trigger('drop')

        expect(router.post).not.toHaveBeenCalled()
    })
})

describe('TableBuilder date filters', () => {
    beforeEach(() => {
        vi.mocked(router.get).mockClear()
        window.history.replaceState({}, '', '/lines')
    })

    function visitedQuery(): URLSearchParams {
        const url = vi.mocked(router.get).mock.calls.at(-1)![0] as string

        return new URLSearchParams(url.split('?')[1])
    }

    it('applies a date range as one from..to value', async () => {
        const table = tableData()
        table.filters = [{key: 'period', label: 'Period', options: {}, value: '2026-10-01..', type: 'date_range'}]

        const wrapper = mount(TableBuilder, {props: {table}, attachTo: document.body})

        await wrapper.find('button').trigger('click')
        await flush()

        const to = document.querySelector<HTMLInputElement>('[data-filter-key="period.to"]')!
        to.value = '2026-10-31'
        to.dispatchEvent(new Event('change'))

        expect(visitedQuery().get('lines_filter[period]')).toBe('2026-10-01..2026-10-31')

        wrapper.unmount()
    })

    function presetTable(value: string | null): TableData {
        const table = tableData()
        table.filters = [{
            key: 'period',
            label: 'Period',
            options: {},
            value,
            type: 'date_range',
            presets: [{key: 'upcoming', label: 'Upcoming'}, {key: 'past', label: 'Past'}],
        }]

        return table
    }

    async function openFilters(table: TableData) {
        const wrapper = mount(TableBuilder, {props: {table}, attachTo: document.body})

        await wrapper.find('button').trigger('click')
        await flush()

        return wrapper
    }

    it('offers the presets and hides the date inputs while a preset is active', async () => {
        const wrapper = await openFilters(presetTable('upcoming'))

        const select = document.querySelector<HTMLSelectElement>('[data-filter-preset="period"]')!

        expect(Array.from(select.options).map((option) => option.textContent?.trim())).toEqual(['any', 'Upcoming', 'Past', 'custom'])
        expect(select.value).toBe('upcoming')
        expect(document.querySelector('[data-filter-key="period.from"]')).toBeNull()

        select.value = 'past'
        select.dispatchEvent(new Event('change'))

        expect(visitedQuery().get('lines_filter[period]')).toBe('past')

        wrapper.unmount()
    })

    it('clears a preset with an empty range so a default preset does not come back', async () => {
        const wrapper = await openFilters(presetTable('upcoming'))

        const select = document.querySelector<HTMLSelectElement>('[data-filter-preset="period"]')!
        select.value = '..'
        select.dispatchEvent(new Event('change'))

        expect(visitedQuery().get('lines_filter[period]')).toBe('..')

        wrapper.unmount()
    })

    it('shows the date inputs for a custom range', async () => {
        const wrapper = await openFilters(presetTable('2026-10-01..2026-10-31'))

        expect(document.querySelector<HTMLSelectElement>('[data-filter-preset="period"]')!.value).toBe('custom')
        expect(document.querySelector<HTMLInputElement>('[data-filter-key="period.from"]')!.value).toBe('2026-10-01')

        wrapper.unmount()
    })
})

describe('TableBuilder totals', () => {
    function tableWithTotals(): TableData {
        const table = tableData()
        table.columns = [column('name'), column('hours'), column('amount')]
        table.totals = {label: 'Total', values: {hours: 12, amount: '&euro; 1.250,00'}}

        return table
    }

    it('renders the totals in a footer under their columns with the label in the first column', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableWithTotals()}})

        const footer = wrapper.find('tfoot[data-totals]')

        expect(footer.find('[data-total-key="name"]').text()).toBe('Total')
        expect(footer.find('[data-total-key="hours"]').text()).toBe('12')
        expect(footer.find('[data-total-key="amount"]').text()).toBe('€ 1.250,00')
    })

    it('lines the footer up with the handle and selection columns', () => {
        const table = tableWithTotals()
        table.bulkActions = [{key: '0', label: 'Delete', url: '/bulk', confirm: false, confirmText: '', confirmButton: '', cancelButton: '', requirePassword: false}]

        const wrapper = mount(TableBuilder, {props: {table, reorderable: true}})

        expect(wrapper.findAll('tfoot td')).toHaveLength(wrapper.findAll('tbody tr')[0].findAll('td').length)
    })

    it('lets a total-{key} slot render a total', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableWithTotals()},
            slots: {'total-hours': ({value}: {value: number}) => h('strong', `${value} h`)},
        })

        expect(wrapper.find('[data-total-key="hours"] strong').text()).toBe('12 h')
    })

    it('renders no footer for an empty table or without totals', () => {
        const empty = tableWithTotals()
        empty.data = []

        expect(mount(TableBuilder, {props: {table: empty}}).find('tfoot').exists()).toBe(false)
        expect(mount(TableBuilder, {props: {table: tableData()}}).find('tfoot').exists()).toBe(false)
    })
})

describe('TableBuilder export', () => {
    const signedUrl = 'https://app.test/table/export?expires=1&table=QXBw&signature=abc'

    function exportableTable(): TableData {
        const table = tableData()
        table.queryKeys = {sort: 'lines_sort', filter: 'lines_filter', page: 'lines_page', perPage: 'lines_perPage', columns: 'lines_columns'}
        table.export = {label: 'Export', url: signedUrl}

        return table
    }

    function clickExport(wrapper: ReturnType<typeof mount>): string {
        const link = wrapper.find('a[data-table-export]')

        link.element.addEventListener('click', (event) => event.preventDefault())
        link.element.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}))

        return link.attributes('href')!
    }

    it('renders an export link only when the table is exportable', () => {
        expect(mount(TableBuilder, {props: {table: tableData()}}).find('[data-table-export]').exists()).toBe(false)

        const link = mount(TableBuilder, {props: {table: exportableTable()}}).find('a[data-table-export]')

        expect(link.text()).toBe('Export')
        expect(link.attributes('download')).toBeDefined()
    })

    it('appends the current sort, search and filters of the page to the signed url', () => {
        window.history.replaceState({}, '', '/lines?lines_sort=-name&lines_filter%5Bstatus%5D=open&signature=page')

        const href = clickExport(mount(TableBuilder, {props: {table: exportableTable()}}))

        expect(href.startsWith(signedUrl + '&')).toBe(true)

        const appended = new URLSearchParams(href.slice(signedUrl.length + 1))

        expect(appended.get('lines_sort')).toBe('-name')
        expect(appended.get('lines_filter[status]')).toBe('open')
        expect(appended.has('signature')).toBe(false)

        window.history.replaceState({}, '', '/')
    })
})

describe('TableBuilder bulk actions', () => {
    const bulkUrl = '/table/bulk-action?signature=x'

    beforeEach(() => {
        vi.mocked(router.post).mockClear()
    })

    function bulkTable(rows = [{id: 'a', name: 'Open'}, {id: 'b', name: 'Second'}]): TableData {
        return {
            ...tableData(),
            data: rows,
            bulkActions: [{key: '0', label: 'Resolve', url: bulkUrl, confirm: false, confirmText: '', confirmButton: '', cancelButton: '', requirePassword: false}],
        } as unknown as TableData
    }

    async function selectFirstRowAndRun(wrapper: ReturnType<typeof mount>) {
        await wrapper.find('tbody [role="checkbox"]').trigger('click')
        await flush()

        const trigger = wrapper.findAll('button').find((button) => button.text() === 'title')!
        await trigger.trigger('click')
        await flush()

        const item = Array.from(document.querySelectorAll<HTMLElement>('[role="menuitem"]')).find((element) => element.textContent?.trim() === 'Resolve')!
        item.click()
        await flush()
    }

    function selectionBar(wrapper: ReturnType<typeof mount>) {
        return wrapper.text().includes('selected_simple')
    }

    function postedOptions(): any {
        return vi.mocked(router.post).mock.calls.at(-1)![2]
    }

    it('clears the selection and renders the reloaded rows after an inertia bulk action', async () => {
        const wrapper = mount(TableBuilder, {props: {table: bulkTable()}, attachTo: document.body})

        await selectFirstRowAndRun(wrapper)

        expect(router.post).toHaveBeenCalledWith(bulkUrl, {ids: ['a']}, expect.objectContaining({preserveScroll: true}))
        expect(selectionBar(wrapper)).toBe(true)

        postedOptions().onSuccess()
        await wrapper.setProps({table: bulkTable([{id: 'a', name: 'Resolved'}, {id: 'b', name: 'Second'}])})

        expect(selectionBar(wrapper)).toBe(false)
        expect(wrapper.find('tbody').text()).toContain('Resolved')
        expect(wrapper.find('tbody').text()).not.toContain('Open')

        wrapper.unmount()
    })

    it('keeps the selection and shows the error when an inertia bulk action fails', async () => {
        const wrapper = mount(TableBuilder, {props: {table: bulkTable()}, attachTo: document.body})

        await selectFirstRowAndRun(wrapper)

        postedOptions().onError({ids: 'Pick at least one row.'})
        await flush()

        expect(selectionBar(wrapper)).toBe(true)
        expect(wrapper.text()).toContain('Pick at least one row.')

        wrapper.unmount()
    })

    function paginatedBulkTable(overrides: Record<string, unknown> = {}): TableData {
        return {
            ...bulkTable(),
            pagination: {current_page: 1, from: 1, to: 2, total: 40, per_page: 2, last_page: 20, links: []},
            ...overrides,
        } as unknown as TableData
    }

    function menuItem(text: string): HTMLElement {
        return Array.from(document.querySelectorAll<HTMLElement>('[role="menuitem"]')).find((element) => element.textContent?.trim() === text)!
    }

    async function selectAllResults(wrapper: ReturnType<typeof mount>) {
        await wrapper.find('thead .cursor-pointer').trigger('click')
        await flush()

        menuItem('select_all_results').click()
        await flush()
    }

    async function runAction(wrapper: ReturnType<typeof mount>, label = 'Resolve') {
        await wrapper.findAll('button').find((button) => button.text() === 'title')!.trigger('click')
        await flush()

        menuItem(label).click()
        await flush()
    }

    it('posts all results with the current state of the page when every result is selected', async () => {
        window.history.replaceState({}, '', '/lines?lines_filter%5Bstatus%5D=open&signature=x')

        const wrapper = mount(TableBuilder, {props: {table: paginatedBulkTable()}, attachTo: document.body})

        await selectAllResults(wrapper)

        expect(wrapper.text()).toContain('selected_simple')

        await runAction(wrapper)

        expect(router.post).toHaveBeenCalledWith(bulkUrl, {ids: ['*'], state: 'lines_filter%5Bstatus%5D=open'}, expect.anything())

        wrapper.unmount()
        window.history.replaceState({}, '', '/')
    })

    it('asks the confirm prop with the texts of the action before posting', async () => {
        const confirm = vi.fn().mockResolvedValue(true)
        const table = bulkTable()
        table.bulkActions[0] = {...table.bulkActions[0], confirm: 'Resolve these errors?', confirmText: 'They disappear from the list.', confirmButton: 'Resolve', cancelButton: 'Keep'}

        const wrapper = mount(TableBuilder, {props: {table, confirm}, attachTo: document.body})

        await selectFirstRowAndRun(wrapper)

        expect(confirm).toHaveBeenCalledWith('Resolve these errors?', {text: 'They disappear from the list.', confirmButton: 'Resolve', cancelButton: 'Keep'})
        expect(router.post).toHaveBeenCalledWith(bulkUrl, {ids: ['a']}, expect.anything())

        wrapper.unmount()
    })

    it('does not post a bulk action that is not confirmed', async () => {
        const confirm = vi.fn().mockResolvedValue(false)
        const table = bulkTable()
        table.bulkActions[0] = {...table.bulkActions[0], confirm: true}

        const wrapper = mount(TableBuilder, {props: {table, confirm}, attachTo: document.body})

        await selectFirstRowAndRun(wrapper)

        expect(confirm).toHaveBeenCalledWith('confirm', expect.anything())
        expect(router.post).not.toHaveBeenCalled()
        expect(selectionBar(wrapper)).toBe(true)

        wrapper.unmount()
    })

    it('falls back to window.confirm with the message and its text', async () => {
        const nativeConfirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
        const table = bulkTable()
        table.bulkActions[0] = {...table.bulkActions[0], confirm: 'Resolve?', confirmText: 'Sure?'}

        const wrapper = mount(TableBuilder, {props: {table}, attachTo: document.body})

        await selectFirstRowAndRun(wrapper)

        expect(nativeConfirm).toHaveBeenCalledWith('Resolve?\n\nSure?')
        expect(router.post).not.toHaveBeenCalled()

        nativeConfirm.mockRestore()
        wrapper.unmount()
    })

    describe('over http', () => {
        const fetchMock = vi.fn()

        beforeEach(() => {
            fetchMock.mockReset()
            vi.stubGlobal('fetch', fetchMock)
        })

        afterEach(() => {
            vi.unstubAllGlobals()
        })

        function mountHttp(fetcher: ReturnType<typeof vi.fn>) {
            return mount(TableBuilder, {
                props: {table: bulkTable([]), transport: 'http', source: '/lines', fetcher},
                attachTo: document.body,
            })
        }

        it('clears the selection and refetches the rows after a successful bulk action', async () => {
            const fetcher = vi.fn()
                .mockResolvedValueOnce({data: [{id: 'a', name: 'Open'}]})
                .mockResolvedValueOnce({data: [{id: 'a', name: 'Resolved'}]})
            fetchMock.mockResolvedValue(new Response(JSON.stringify({performed: true}), {status: 200}))

            const wrapper = mountHttp(fetcher)
            await flush()

            await selectFirstRowAndRun(wrapper)
            await flush()

            expect(fetchMock).toHaveBeenCalledWith(bulkUrl, expect.objectContaining({method: 'POST', body: JSON.stringify({ids: ['a']})}))
            expect(fetcher).toHaveBeenCalledTimes(2)
            expect(selectionBar(wrapper)).toBe(false)
            expect(wrapper.find('tbody').text()).toContain('Resolved')

            wrapper.unmount()
        })

        it('posts all results with the sort, search and filters it is showing', async () => {
            const fetcher = vi.fn().mockResolvedValue({
                data: [{id: 'a', name: 'Open'}, {id: 'b', name: 'Second'}],
                pagination: {current_page: 1, from: 1, to: 2, total: 40, per_page: 2, last_page: 20, links: []},
            })
            fetchMock.mockResolvedValue(new Response(JSON.stringify({performed: true}), {status: 200}))

            const table = {
                ...bulkTable([]),
                filters: [{key: 'status', label: 'Status', options: {open: 'Open'}, value: 'open', type: 'select'}],
            } as unknown as TableData

            const wrapper = mount(TableBuilder, {
                props: {table, transport: 'http', source: '/lines', fetcher},
                attachTo: document.body,
            })
            await flush()

            await selectAllResults(wrapper)
            await runAction(wrapper)
            await flush()

            expect(fetchMock).toHaveBeenCalledWith(bulkUrl, expect.objectContaining({
                body: JSON.stringify({ids: ['*'], state: 'lines_filter%5Bstatus%5D=open'}),
            }))

            wrapper.unmount()
        })

        it('keeps the selection and shows the server error when the bulk action fails', async () => {
            const fetcher = vi.fn().mockResolvedValue({data: [{id: 'a', name: 'Open'}]})
            fetchMock.mockResolvedValue(new Response(JSON.stringify({message: 'Invalid', errors: {ids: ['Pick at least one row.']}}), {status: 422}))

            const wrapper = mountHttp(fetcher)
            await flush()

            await selectFirstRowAndRun(wrapper)
            await flush()

            expect(fetcher).toHaveBeenCalledTimes(1)
            expect(selectionBar(wrapper)).toBe(true)
            expect(wrapper.text()).toContain('Pick at least one row.')

            wrapper.unmount()
        })
    })
})

describe('TableBuilder row keys and selection', () => {
    const bulkUrl = '/table/bulk-action?signature=x'

    beforeEach(() => {
        vi.mocked(router.post).mockClear()
    })

    function keyedTable(rows: any[], overrides: Record<string, unknown> = {}): TableData {
        return {
            ...tableData(),
            data: rows,
            bulkActions: [{key: '0', label: 'Resolve', url: bulkUrl, confirm: false, confirmText: '', confirmButton: '', cancelButton: '', requirePassword: false}],
            filters: [{key: 'status', label: 'Status', options: {}, value: 'open', type: 'select'}],
            searchInputs: {global: {key: 'global', label: 'Search', value: ''}},
            pagination: {current_page: 1, from: 1, to: 2, total: 4, per_page: 2, last_page: 2, links: []},
            ...overrides,
        } as unknown as TableData
    }

    const uuidRows = [{uuid: 'u-1', name: 'First'}, {uuid: 'u-2', name: 'Second'}]

    function rowCheckboxes(wrapper: ReturnType<typeof mount>) {
        return wrapper.findAll('tbody [role="checkbox"]')
    }

    function checkedStates(wrapper: ReturnType<typeof mount>): string[] {
        return rowCheckboxes(wrapper).map((checkbox) => checkbox.attributes('data-state') ?? '')
    }

    function menuItem(text: string): HTMLElement {
        return Array.from(document.querySelectorAll<HTMLElement>('[role="menuitem"]')).find((element) => element.textContent?.trim() === text)!
    }

    async function runAction(wrapper: ReturnType<typeof mount>) {
        await wrapper.findAll('button').find((button) => button.text() === 'title')!.trigger('click')
        await flush()

        menuItem('Resolve').click()
        await flush()
    }

    function postedIds(): unknown {
        return (vi.mocked(router.post).mock.calls.at(-1)![1] as any).ids
    }

    it('posts the values of the row key of the table', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(uuidRows, {rowKey: 'uuid'})}, attachTo: document.body})

        await rowCheckboxes(wrapper)[1].trigger('click')
        await runAction(wrapper)

        expect(postedIds()).toEqual(['u-2'])

        wrapper.unmount()
    })

    it('posts a row whose key is zero', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable([{id: 0, name: 'Zero'}, {id: 1, name: 'One'}])}, attachTo: document.body})

        await rowCheckboxes(wrapper)[0].trigger('click')
        await runAction(wrapper)

        expect(postedIds()).toEqual([0])

        wrapper.unmount()
    })

    it('keeps the selection on its row when the rows come back in another order', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(uuidRows, {rowKey: 'uuid'})}, attachTo: document.body})

        await rowCheckboxes(wrapper)[0].trigger('click')
        await wrapper.setProps({table: keyedTable([...uuidRows].reverse(), {rowKey: 'uuid'})})

        expect(checkedStates(wrapper)).toEqual(['unchecked', 'checked'])

        wrapper.unmount()
    })

    it('keeps the selection across pages and posts the rows of every page', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(uuidRows, {rowKey: 'uuid'})}, attachTo: document.body})

        await rowCheckboxes(wrapper)[0].trigger('click')
        await wrapper.setProps({table: keyedTable([{uuid: 'u-3', name: 'Third'}, {uuid: 'u-4', name: 'Fourth'}], {
            rowKey: 'uuid',
            pagination: {current_page: 2, from: 3, to: 4, total: 4, per_page: 2, last_page: 2, links: []},
        })})

        expect(checkedStates(wrapper)).toEqual(['unchecked', 'unchecked'])

        await rowCheckboxes(wrapper)[1].trigger('click')
        await runAction(wrapper)

        expect(postedIds()).toEqual(['u-1', 'u-4'])

        wrapper.unmount()
    })

    it('clears the selection when a filter changes', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(uuidRows, {rowKey: 'uuid'})}, attachTo: document.body})

        await rowCheckboxes(wrapper)[0].trigger('click')
        await wrapper.setProps({table: keyedTable(uuidRows, {
            rowKey: 'uuid',
            filters: [{key: 'status', label: 'Status', options: {}, value: 'closed', type: 'select'}],
        })})

        expect(checkedStates(wrapper)).toEqual(['unchecked', 'unchecked'])
        expect(wrapper.text()).not.toContain('selected_simple')

        wrapper.unmount()
    })

    it('clears all selected results when the search changes', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(uuidRows, {rowKey: 'uuid'})}, attachTo: document.body})

        await wrapper.find('thead .cursor-pointer').trigger('click')
        await flush()
        menuItem('select_all_results').click()
        await flush()

        expect(checkedStates(wrapper)).toEqual(['checked', 'checked'])

        await wrapper.setProps({table: keyedTable(uuidRows, {
            rowKey: 'uuid',
            searchInputs: {global: {key: 'global', label: 'Search', value: 'sec'}},
        })})

        expect(checkedStates(wrapper)).toEqual(['unchecked', 'unchecked'])
        expect(wrapper.text()).not.toContain('selected_simple')

        wrapper.unmount()
    })

    it('emits the row key with row-click', async () => {
        const onRowClick = vi.fn()
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(uuidRows, {rowKey: 'uuid', bulkActions: []}), onRowClick}})

        await wrapper.findAll('tbody tr')[1].find('[data-column-key="name"]').trigger('click')

        expect(onRowClick.mock.calls[0][0]).toMatchObject({index: 1, key: 'u-2'})
    })

    it('posts the uuid of a moved row', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(uuidRows, {
            rowKey: 'uuid',
            bulkActions: [],
            reorder: {column: 'sort_order', key: 'uuid', url: '/reorder'},
        })}})

        await wrapper.findAll('[data-reorder-handle]')[1].trigger('dragstart', {dataTransfer: dataTransfer()})
        await wrapper.findAll('tbody tr')[0].trigger('dragover')
        await wrapper.findAll('tbody tr')[0].trigger('drop')

        expect(router.post).toHaveBeenCalledWith('/reorder', {id: 'u-2', to: 'u-1'}, expect.anything())
    })

    const escapedRows = [
        {code: 'A&amp;B', __key: 'A&B', name: 'First'},
        {code: '&lt;2&gt;', __key: '<2>', name: 'Second'},
    ]

    it('posts the raw key of a row instead of its escaped cell value', async () => {
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(escapedRows, {rowKey: 'code'})}, attachTo: document.body})

        await rowCheckboxes(wrapper)[0].trigger('click')
        await runAction(wrapper)

        expect(postedIds()).toEqual(['A&B'])

        wrapper.unmount()
    })

    it('emits and reorders by the raw key of a row', async () => {
        const onRowClick = vi.fn()
        const wrapper = mount(TableBuilder, {props: {table: keyedTable(escapedRows, {
            rowKey: 'code',
            bulkActions: [],
            reorder: {column: 'sort_order', key: 'code', url: '/reorder'},
        }), onRowClick}})

        await wrapper.findAll('tbody tr')[1].find('[data-column-key="name"]').trigger('click')

        expect(onRowClick.mock.calls[0][0]).toMatchObject({key: '<2>'})

        await wrapper.findAll('[data-reorder-handle]')[1].trigger('dragstart', {dataTransfer: dataTransfer()})
        await wrapper.findAll('tbody tr')[0].trigger('dragover')
        await wrapper.findAll('tbody tr')[0].trigger('drop')

        expect(router.post).toHaveBeenCalledWith('/reorder', {id: '<2>', to: 'A&B'}, expect.anything())
    })
})

describe('TableBuilder toolbar', () => {
    it('gives the filter toggle an accessible name', () => {
        const table = tableData()
        table.filters = [{key: 'status', label: 'Status', options: {open: 'Open'}, value: null, type: 'select'}]

        const toggle = mount(TableBuilder, {props: {table}}).find('[data-filter-toggle]')

        expect(toggle.attributes('aria-label')).toBe('toggle')
    })

    it('shows the column toggle while a column can be hidden', () => {
        const table = tableData()
        table.columns = [{...column('name'), can_be_hidden: true}]

        expect(mount(TableBuilder, {props: {table}}).text()).toContain('columns')
    })

    it('hides the column toggle when the table turns it off or no column can be hidden', () => {
        const turnedOff = tableData()
        turnedOff.columns = [{...column('name'), can_be_hidden: true}]
        turnedOff.columnToggle = false

        expect(mount(TableBuilder, {props: {table: turnedOff}}).text()).not.toContain('columns')
        expect(mount(TableBuilder, {props: {table: tableData()}}).text()).not.toContain('columns')
    })
})

describe('TableBuilder boolean columns', () => {
    afterEach(() => {
        page.props = {}
    })

    it('names the boolean icons with the translated yes and no', () => {
        page.props = {vue_table_builder_table_translations: {export: {yes: 'Ja', no: 'Nee'}}}

        const table = tableData()
        table.data = [{id: 'a', name: 'First', done: true}, {id: 'b', name: 'Second', done: false}]
        table.columns = [{...column('done'), boolean: true}]

        const labels = mount(TableBuilder, {props: {table}})
            .findAll('tbody [role="img"]')
            .map((icon) => icon.attributes('aria-label'))

        expect(labels).toEqual(['Ja', 'Nee'])
    })
})

describe('TableBuilder test hooks', () => {
    it('marks every data row and the empty state', () => {
        const filled = mount(TableBuilder, {props: {table: tableData()}})
        const empty = mount(TableBuilder, {props: {table: {...tableData(), data: []}}})

        expect(filled.findAll('[data-testid="table-row"]')).toHaveLength(3)
        expect(empty.find('[data-testid="table-empty"]').exists()).toBe(true)
    })

    it('gives each text filter a test id behind the filter toggle', () => {
        const table = {...tableData(), filters: [{key: 'name', label: 'Name', type: 'text', value: ''}]}
        const wrapper = mount(TableBuilder, {props: {table}})

        expect(wrapper.find('[data-testid="table-filter"]').exists()).toBe(true)
    })
})
