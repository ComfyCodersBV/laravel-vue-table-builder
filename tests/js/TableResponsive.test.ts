import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest'
import {router} from '@inertiajs/vue3'
import {mount} from '@vue/test-utils'
import {nextTick} from 'vue'
import TableBuilder from '../../resources/js/components/TableBuilder.vue'
import type {Column, RowAction, TableData} from '../../resources/js/types/table-builder'

const page = vi.hoisted(() => ({props: {} as Record<string, unknown>}))

vi.mock('@inertiajs/vue3', () => ({
    router: {get: vi.fn(), reload: vi.fn(), post: vi.fn(), visit: vi.fn(), replace: vi.fn()},
    usePage: () => page,
}))

function column(key: string, overrides: Partial<Column> = {}): Column {
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
        ...overrides,
    }
}

function rowAction(overrides: Partial<RowAction> = {}): RowAction {
    return {
        key: 'edit',
        label: 'Edit',
        url: '/lines/a/edit',
        method: 'post',
        confirm: null,
        variant: 'outline',
        disabled: false,
        disabledReason: null,
        ...overrides,
    }
}

function tableData(overrides: Partial<TableData> = {}): TableData {
    return {
        schemaVersion: '1.4',
        name: 'lines',
        data: [{id: 'a', name: 'First', email: 'first@example.com'}, {id: 'b', name: 'Second', email: 'second@example.com'}],
        columns: [column('name', {label: 'Name'}), column('email', {label: 'Email', hide_below: 'md'})],
        filters: [],
        searchInputs: {},
        perPageOptions: [],
        defaultSort: '',
        bulkActions: [],
        rowLinks: [],
        rowLinkType: '',
        rowLinkTarget: '_self',
        layout: 'table',
        defaultLayout: 'table',
        layoutToggle: false,
        gridBelow: null,
        queryKeys: {sort: 'lines_sort', filter: 'lines_filter', page: 'lines_page', perPage: 'lines_perPage', columns: 'lines_columns', layout: 'lines_layout'},
        ...overrides,
    } as TableData
}

function flush(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, 0))
}

type Listener = (event: {matches: boolean}) => void

let narrow = false
const listeners: Listener[] = []
const queries: string[] = []

function setNarrow(value: boolean): void {
    narrow = value
    listeners.forEach((listener) => listener({matches: value}))
}

beforeEach(() => {
    narrow = false
    listeners.length = 0
    queries.length = 0
    vi.mocked(router.replace).mockClear()
    window.history.replaceState({}, '', '/lines')
    window.matchMedia = vi.fn((query: string) => {
        queries.push(query)

        return {
            matches: query.startsWith('not all and') && narrow,
            media: query,
            onchange: null,
            addEventListener: (_type: string, listener: Listener) => listeners.push(listener),
            removeEventListener: () => {},
            addListener: () => {},
            removeListener: () => {},
            dispatchEvent: () => true,
        }
    }) as unknown as typeof window.matchMedia
})

afterEach(() => {
    window.history.replaceState({}, '', '/')
    delete (window as {matchMedia?: unknown}).matchMedia
})

describe('column header classes', () => {
    it('puts the header classes on the header cell and the cell classes on the body cells only', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData({columns: [column('name', {class: 'font-mono', head_class: 'w-40'})]})},
        })

        expect(wrapper.find('th[data-column-key="name"]').classes()).toContain('w-40')
        expect(wrapper.find('th[data-column-key="name"]').classes()).not.toContain('font-mono')
        expect(wrapper.find('td[data-column-key="name"]').classes()).toContain('font-mono')
        expect(wrapper.find('td[data-column-key="name"]').classes()).not.toContain('w-40')
    })
})

describe('hiding a column below a breakpoint', () => {
    it('hides the header, the cells and the total below the breakpoint', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData({totals: {label: 'Total', values: {email: '2'}}})},
        })

        for (const cell of [
            wrapper.find('th[data-column-key="email"]'),
            wrapper.find('td[data-column-key="email"]'),
            wrapper.find('[data-total-key="email"]'),
        ]) {
            expect(cell.classes()).toEqual(expect.arrayContaining(['hidden', 'md:table-cell']))
        }

        expect(wrapper.find('th[data-column-key="name"]').classes()).not.toContain('hidden')
        expect(wrapper.find('td[data-column-key="name"]').classes()).not.toContain('hidden')
    })

    it.each([
        ['sm', 'sm:table-cell'],
        ['lg', 'lg:table-cell'],
        ['xl', 'xl:table-cell'],
    ] as const)('uses the static classes for %s', (breakpoint, visibleFrom) => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData({columns: [column('name'), column('email', {hide_below: breakpoint})]})},
        })

        expect(wrapper.find('td[data-column-key="email"]').classes()).toEqual(expect.arrayContaining(['hidden', visibleFrom]))
    })

    it('hides the meta line of the tile below the breakpoint but keeps the title', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData({layout: 'grid', defaultLayout: 'grid', columns: [column('name', {hide_below: 'md'}), column('email', {hide_below: 'md'})]})},
        })

        const title = wrapper.find('[data-tile-title]')
        const meta = wrapper.find('[data-tile-meta]')

        expect(title.classes()).not.toContain('hidden')
        expect(meta.classes()).toEqual(expect.arrayContaining(['hidden', 'md:flex']))
        expect(meta.classes()).not.toContain('flex')
    })

    it('shows a column at every width once the user turns it on in the columns menu', async () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData({columns: [column('name'), column('email', {hide_below: 'md', can_be_hidden: true})]})},
            global: {
                stubs: {
                    DropdownMenu: {template: '<div><slot/></div>'},
                    DropdownMenuTrigger: {template: '<div><slot/></div>'},
                    DropdownMenuContent: {template: '<div><slot/></div>'},
                    DropdownMenuCheckboxItem: {
                        props: ['modelValue'],
                        emits: ['update:modelValue'],
                        template: '<button type="button" data-column-option @click="$emit(\'update:modelValue\', !modelValue)"><slot/></button>',
                    },
                },
            },
        })

        const option = wrapper.find('[data-column-option]')

        await option.trigger('click')

        expect(wrapper.find('td[data-column-key="email"]').exists()).toBe(false)

        await option.trigger('click')

        expect(wrapper.find('td[data-column-key="email"]').classes()).not.toContain('hidden')
        expect(wrapper.find('th[data-column-key="email"]').classes()).not.toContain('hidden')
    })
})

describe('the first visible column', () => {
    it('keeps showing with its row link when it has a breakpoint', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData({columns: [column('name', {hide_below: 'md'}), column('email', {hide_below: 'md'})], rowLinks: ['/lines/a', '/lines/b'], rowLinkType: 'link'})},
        })

        expect(wrapper.find('th[data-column-key="name"]').classes()).not.toContain('hidden')
        expect(wrapper.find('td[data-column-key="name"]').classes()).not.toContain('hidden')
        expect(wrapper.find('td[data-column-key="name"] [data-row-link]').exists()).toBe(true)
        expect(wrapper.find('td[data-column-key="email"]').classes()).toContain('hidden')
    })
})

describe('tiles below a breakpoint', () => {
    it('shows one tile per row on phones and two from sm', () => {
        narrow = true

        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md'})}})

        expect(wrapper.find('[data-testid="table-tile"]').element.parentElement!.className).toContain('grid-cols-1 sm:grid-cols-2')
    })

    it('keeps the grid-class prop below the breakpoint', () => {
        narrow = true

        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md'}), gridClass: 'grid-cols-3'}})

        expect(wrapper.find('[data-testid="table-tile"]').element.parentElement!.className).toContain('grid-cols-3')
    })

    it('selects the default order when the default sort is not a sortable column', () => {
        narrow = true

        const wrapper = mount(TableBuilder, {
            props: {table: tableData({gridBelow: 'md', defaultSort: '-id', columns: [column('name', {sortable: true}), column('email')]})},
        })

        const select = wrapper.find('[data-table-sort]').element as HTMLSelectElement

        expect(select.value).toBe('')
        expect(select.options[0].value).toBe('')
    })

    it('keeps the default order option after the user picks a sort', () => {
        narrow = true

        const wrapper = mount(TableBuilder, {
            props: {table: tableData({gridBelow: 'md', defaultSort: '-id', columns: [column('name', {sortable: true, sorted: 'asc'}), column('email')]})},
        })

        const select = wrapper.find('[data-table-sort]').element as HTMLSelectElement

        expect(select.value).toBe('name')
        expect(select.options[0].value).toBe('')
    })

    it('keeps the wide grid columns for a grid table on a wide screen', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md', layout: 'grid', defaultLayout: 'grid'})}})

        expect(wrapper.find('[data-testid="table-tile"]').element.parentElement!.className).toContain('grid-cols-2 md:grid-cols-3')
    })

    it('renders the table without matchMedia', () => {
        delete (window as {matchMedia?: unknown}).matchMedia

        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md'})}})

        expect(wrapper.find('table').exists()).toBe(true)
    })

    it('leaves the url alone with the http transport', async () => {
        narrow = true

        const wrapper = mount(TableBuilder, {
            props: {table: tableData({gridBelow: 'md', layoutToggle: true}), transport: 'http', source: '/api/lines', fetcher: vi.fn()},
        })

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)

        await wrapper.find('[data-layout="table"]').trigger('click')

        expect(wrapper.find('table').exists()).toBe(true)
        expect(router.replace).not.toHaveBeenCalled()
    })

    it('keeps a layout from the url that equals the automatic one', async () => {
        narrow = true
        window.history.replaceState({}, '', '/lines?lines_layout=grid')

        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md', layoutToggle: true})}})

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)

        setNarrow(false)
        await nextTick()

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)
    })

    it('renders the table on wide screens', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md'})}})

        expect(wrapper.find('table').exists()).toBe(true)
        expect(wrapper.find('[data-table-grid]').exists()).toBe(false)
        expect(queries).toContain('not all and (min-width: 48rem)')
    })

    it('renders tiles below the breakpoint without a toggle', () => {
        narrow = true

        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md'})}})

        expect(wrapper.find('table').exists()).toBe(false)
        expect(wrapper.findAll('[data-testid="table-tile"]')).toHaveLength(2)
        expect(wrapper.find('[data-layout-toggle]').exists()).toBe(false)
    })

    it('follows the screen when it crosses the breakpoint', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'lg'})}})

        expect(wrapper.find('table').exists()).toBe(true)
        expect(queries).toContain('not all and (min-width: 64rem)')

        setNarrow(true)
        await nextTick()

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)

        setNarrow(false)
        await nextTick()

        expect(wrapper.find('table').exists()).toBe(true)
    })

    it('renders the tile slot below the breakpoint', () => {
        narrow = true

        const wrapper = mount(TableBuilder, {
            props: {table: tableData({gridBelow: 'md'})},
            slots: {tile: '<p class="custom-tile">{{ params.row.name }}</p>'},
        })

        expect(wrapper.findAll('.custom-tile').map((tile) => tile.text())).toEqual(['First', 'Second'])
    })

    it('ignores the screen width without gridBelow', () => {
        narrow = true

        const wrapper = mount(TableBuilder, {props: {table: tableData()}})

        expect(wrapper.find('table').exists()).toBe(true)
    })

    it('shows the toggle state of the automatic layout and keeps a choice against it in the url', async () => {
        narrow = true

        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md', layoutToggle: true})}})

        expect(wrapper.find('[data-layout="grid"]').attributes('aria-pressed')).toBe('true')

        await wrapper.find('[data-layout="table"]').trigger('click')

        expect(wrapper.find('table').exists()).toBe(true)
        expect(router.replace).toHaveBeenLastCalledWith(expect.objectContaining({url: '/lines?lines_layout=table'}))

        await wrapper.find('[data-layout="grid"]').trigger('click')

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)
        expect(router.replace).toHaveBeenLastCalledWith(expect.objectContaining({url: '/lines'}))
    })

    it('keeps the layout the user chose in the url below the breakpoint', () => {
        narrow = true
        window.history.replaceState({}, '', '/lines?lines_layout=table')

        const wrapper = mount(TableBuilder, {props: {table: tableData({gridBelow: 'md', layoutToggle: true})}})

        expect(wrapper.find('table').exists()).toBe(true)
    })
})

describe('touch targets', () => {
    it('gives a labelled row action a taller button and an icon only one a larger square on coarse pointers', async () => {
        const {registerRowActionIcons} = await import('../../resources/js/lib/row-action-icons')
        registerRowActionIcons({'touch-pencil': {render: () => null}})

        const wrapper = mount(TableBuilder, {
            props: {table: tableData({rowActions: [[rowAction(), rowAction({key: 'open', label: 'Open', icon: 'touch-pencil', iconOnly: true})], []]})},
        })

        expect(wrapper.find('[data-row-action="edit"]').classes()).toContain('pointer-coarse:h-10')
        expect(wrapper.find('[data-row-action="open"]').classes()).toContain('pointer-coarse:size-10')
    })
})
