import {beforeEach, describe, expect, it, vi} from 'vitest'
import {router} from '@inertiajs/vue3'
import {mount} from '@vue/test-utils'
import {h} from 'vue'
import TableBuilder from '../../resources/js/components/TableBuilder.vue'
import type {Column, RowAction, TableData} from '../../resources/js/types/table-builder'

vi.mock('@inertiajs/vue3', () => ({
    router: {get: vi.fn(), reload: vi.fn(), post: vi.fn(), visit: vi.fn()},
    usePage: () => ({props: {}}),
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

    it('does not run an action that is not confirmed', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableWithActions(), confirm: () => false}})

        await wrapper.find('button[data-row-action="delete"]').trigger('click')
        await flush()

        expect(router.visit).not.toHaveBeenCalled()
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
})
