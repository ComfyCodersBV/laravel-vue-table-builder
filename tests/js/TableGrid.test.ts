import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest'
import {router} from '@inertiajs/vue3'
import {mount} from '@vue/test-utils'
import {defineComponent, h} from 'vue'
import TableBuilder from '../../resources/js/components/TableBuilder.vue'
import {registerRowActionIcons} from '../../resources/js/lib/row-action-icons'
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
        method: 'get',
        confirm: null,
        variant: 'outline',
        disabled: false,
        disabledReason: null,
        ...overrides,
    }
}

function icon(name: string) {
    return defineComponent({render: () => h('svg', {'data-icon': name})})
}

function gridData(overrides: Partial<TableData> = {}): TableData {
    return {
        name: 'lines',
        data: [{id: 'a', name: 'First', status: 'open'}, {id: 'b', name: 'Second', status: 'done'}],
        columns: [column('name', {label: 'Name', sortable: true}), column('status', {label: 'Status'})],
        filters: [],
        searchInputs: {},
        perPageOptions: [],
        defaultSort: '',
        bulkActions: [],
        rowLinks: [],
        rowLinkType: '',
        rowLinkTarget: '_self',
        layout: 'grid',
        defaultLayout: 'grid',
        layoutToggle: false,
        queryKeys: {sort: 'lines_sort', filter: 'lines_filter', page: 'lines_page', perPage: 'lines_perPage', columns: 'lines_columns', layout: 'lines_layout'},
        ...overrides,
    } as TableData
}

function flush(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
    vi.mocked(router.get).mockClear()
    vi.mocked(router.visit).mockClear()
    vi.mocked(router.replace).mockClear()
    window.history.replaceState({}, '', '/lines')
})

afterEach(() => {
    window.history.replaceState({}, '', '/')
})

describe('TableBuilder grid', () => {
    it('renders a tile per row with the first column as title and the others as meta lines', () => {
        const wrapper = mount(TableBuilder, {props: {table: gridData()}})

        const tiles = wrapper.findAll('[data-testid="table-tile"]')

        expect(wrapper.find('table').exists()).toBe(false)
        expect(tiles).toHaveLength(2)
        expect(tiles[0].find('[data-tile-title]').text()).toBe('First')
        expect(tiles[0].find('[data-tile-meta]').text()).toBe('Status:open')
    })

    it('renders the rows as a table by default', () => {
        const wrapper = mount(TableBuilder, {props: {table: gridData({layout: undefined, defaultLayout: undefined})}})

        expect(wrapper.find('table').exists()).toBe(true)
        expect(wrapper.find('[data-table-grid]').exists()).toBe(false)
    })

    it('renders the tile slot with the row, its actions and the run function', async () => {
        const table = gridData({rowActions: [[rowAction({key: 'done', label: 'Done', url: '/lines/a/done', method: 'post'})], []]})

        const wrapper = mount(TableBuilder, {
            props: {table},
            slots: {
                tile: ({row, actions, run}: {row: any; actions: RowAction[]; run: (action: RowAction) => void}) =>
                    h('button', {class: 'custom', onClick: () => run(actions[0])}, `${row.name} ${actions.length}`),
            },
        })

        expect(wrapper.findAll('.custom').map((tile) => tile.text())).toEqual(['First 1', 'Second 0'])

        await wrapper.find('.custom').trigger('click')
        await flush()

        expect(router.visit).toHaveBeenCalledWith('/lines/a/done', expect.objectContaining({method: 'post'}))
    })

    it('renders the row actions with an icon as icon only buttons in the tile', () => {
        registerRowActionIcons({'grid-pencil': icon('grid-pencil')})
        const table = gridData({rowActions: [[rowAction({icon: 'grid-pencil'}), rowAction({key: 'archive', label: 'Archive'})], []]})

        const wrapper = mount(TableBuilder, {props: {table}})

        const edit = wrapper.find('[data-tile-actions] [data-row-action="edit"]')
        const archive = wrapper.find('[data-tile-actions] [data-row-action="archive"]')

        expect(edit.attributes('aria-label')).toBe('Edit')
        expect(edit.text()).toBe('')
        expect(archive.text()).toBe('Archive')
    })

    it('follows the row link when a tile is clicked', async () => {
        const wrapper = mount(TableBuilder, {props: {table: gridData({rowLinks: ['/lines/a', '/lines/b'], rowLinkType: 'link'})}})

        expect(wrapper.find('[data-testid="table-tile"] a[data-row-link]').attributes('href')).toBe('/lines/a')

        await wrapper.findAll('[data-testid="table-tile"]')[1].trigger('click')

        expect(router.visit).toHaveBeenCalledWith('/lines/b')
    })

    it('does not follow the row link from the checkbox, a row action or an unclickable column', async () => {
        const table = gridData({
            rowLinks: ['/lines/a', '/lines/b'],
            rowLinkType: 'link',
            columns: [column('name', {label: 'Name'}), column('status', {label: 'Status', clickable: false})],
            bulkActions: [{key: '0', label: 'Resolve', url: '/bulk', confirm: false, confirmText: '', confirmButton: '', cancelButton: '', requirePassword: false}],
            rowActions: [[rowAction({key: 'done', label: 'Done', url: '/lines/a/done', method: 'post'})], []],
        })
        vi.mocked(router.visit).mockImplementation((() => undefined) as never)

        const wrapper = mount(TableBuilder, {props: {table}})

        await wrapper.find('[data-tile-checkbox]').trigger('click')
        await wrapper.find('[data-tile-meta]').trigger('click')

        expect(router.visit).not.toHaveBeenCalled()

        await wrapper.find('[data-tile-actions] [data-row-action="done"]').trigger('click')
        await flush()

        expect(router.visit).toHaveBeenCalledTimes(1)
        expect(router.visit).toHaveBeenCalledWith('/lines/a/done', expect.anything())

        vi.mocked(router.visit).mockReset()
    })

    it('takes its own grid column classes', () => {
        const wrapper = mount(TableBuilder, {props: {table: gridData(), gridClass: 'grid-cols-1'}})

        const grid = wrapper.find('[data-testid="table-tile"]').element.parentElement!

        expect(grid.className).toContain('grid-cols-1')
        expect(grid.className).not.toContain('md:grid-cols-3')
    })

    it('renders a boolean title column as an icon', () => {
        const table = gridData({
            columns: [column('locked', {boolean: true}), column('name')],
            data: [{id: 'a', locked: true, name: 'First'}],
        })

        const wrapper = mount(TableBuilder, {props: {table}})

        expect(wrapper.find('[data-tile-title] svg').exists()).toBe(true)
    })

    it('emits row-click for a tile without a link', async () => {
        const onRowClick = vi.fn()
        const wrapper = mount(TableBuilder, {props: {table: gridData(), onRowClick}})

        await wrapper.find('[data-testid="table-tile"]').trigger('click')

        expect(onRowClick).toHaveBeenCalledWith(expect.objectContaining({index: 0, key: 'a'}))
    })

    it('selects a tile with its checkbox for the bulk actions', async () => {
        const table = gridData({
            bulkActions: [{key: '0', label: 'Resolve', url: '/bulk', confirm: false, confirmText: '', confirmButton: '', cancelButton: '', requirePassword: false}],
        })

        page.props = {vue_table_builder_table_translations: {select_row: 'Select :label'}}

        const wrapper = mount(TableBuilder, {props: {table}})

        page.props = {}

        const checkbox = wrapper.findAll('[data-tile-checkbox]')[1]

        expect(checkbox.attributes('aria-label')).toBe('Select Second')

        await checkbox.trigger('click')

        expect(wrapper.text()).toContain('selected_simple')
        expect(wrapper.findAll('[data-testid="table-tile"]')[1].classes()).toContain('ring-2')
        expect(router.visit).not.toHaveBeenCalled()
    })

    it('shows the empty state in the grid', () => {
        const wrapper = mount(TableBuilder, {props: {table: gridData({data: [], emptyState: {title: 'No lines yet'}})}})

        expect(wrapper.find('[data-empty-state]').text()).toBe('No lines yet')
    })

    it('sorts through the sort select because the grid has no headers', async () => {
        const wrapper = mount(TableBuilder, {props: {table: gridData()}})

        const select = wrapper.find('select[data-table-sort]')

        expect(select.findAll('option').map((option) => option.attributes('value'))).toEqual(['', 'name', '-name'])

        await select.setValue('-name')

        expect(router.get).toHaveBeenCalledWith('/lines?lines_sort=-name', {}, expect.anything())
    })

    it('shows the current sort in the sort select', () => {
        window.history.replaceState({}, '', '/lines?lines_sort=-name')

        const wrapper = mount(TableBuilder, {
            props: {table: gridData({columns: [column('name', {sortable: true, sorted: 'desc'}), column('status')]})},
        })

        expect((wrapper.find('select[data-table-sort]').element as HTMLSelectElement).value).toBe('-name')
    })

    it('shows no sort select in the table layout', () => {
        const wrapper = mount(TableBuilder, {props: {table: gridData({layout: 'table', defaultLayout: 'table'})}})

        expect(wrapper.find('select[data-table-sort]').exists()).toBe(false)
    })
})

describe('TableBuilder layout toggle', () => {
    function toggleData(): TableData {
        return gridData({layout: 'table', defaultLayout: 'table', layoutToggle: true})
    }

    it('switches to the grid and keeps the choice in the url with a replace', async () => {
        window.history.replaceState({}, '', '/lines?lines_page=2')

        const wrapper = mount(TableBuilder, {props: {table: toggleData()}})

        expect(wrapper.find('table').exists()).toBe(true)

        await wrapper.find('[data-layout-toggle] [data-layout="grid"]').trigger('click')

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)
        expect(wrapper.find('[data-layout="grid"]').attributes('aria-pressed')).toBe('true')
        expect(router.replace).toHaveBeenCalledWith({url: '/lines?lines_page=2&lines_layout=grid', preserveState: true, preserveScroll: true})
        expect(router.get).not.toHaveBeenCalled()
    })

    it('drops the layout from the url when switching back to the default', async () => {
        window.history.replaceState({}, '', '/lines?lines_layout=grid')

        const wrapper = mount(TableBuilder, {props: {table: toggleData()}})

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)

        await wrapper.find('[data-layout="table"]').trigger('click')

        expect(router.replace).toHaveBeenCalledWith({url: '/lines', preserveState: true, preserveScroll: true})
    })

    it('keeps the layout out of the url with the http transport', async () => {
        window.history.replaceState({}, '', '/lines?lines_layout=grid')

        const fetcher = vi.fn().mockResolvedValue({data: [], pagination: null})
        const wrapper = mount(TableBuilder, {props: {table: toggleData(), transport: 'http', source: '/rows', fetcher}})

        expect(wrapper.find('table').exists()).toBe(true)

        await wrapper.find('[data-layout="grid"]').trigger('click')

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)
        expect(router.replace).not.toHaveBeenCalled()
    })

    it('ignores an invalid layout in the url', () => {
        window.history.replaceState({}, '', '/lines?lines_layout=cards')

        const wrapper = mount(TableBuilder, {props: {table: toggleData()}})

        expect(wrapper.find('table').exists()).toBe(true)
    })

    it('ignores the layout in the url without the toggle', () => {
        window.history.replaceState({}, '', '/lines?lines_layout=table')

        const wrapper = mount(TableBuilder, {props: {table: gridData()}})

        expect(wrapper.find('[data-table-grid]').exists()).toBe(true)
        expect(wrapper.find('[data-layout-toggle]').exists()).toBe(false)
    })
})

describe('TableBuilder icon only row actions', () => {
    it('renders only the icon with the label as aria-label and tooltip', () => {
        registerRowActionIcons({'only-trash': icon('only-trash')})
        const table = gridData({
            layout: 'table',
            rowActions: [[
                rowAction({key: 'delete', label: 'Delete', url: '/lines/a', method: 'delete', icon: 'only-trash', iconOnly: true}),
                rowAction({key: 'stop', label: 'Stop', method: 'put', icon: 'only-trash', iconOnly: true, disabled: true, disabledReason: 'Busy'}),
                rowAction({key: 'show', label: 'Show', icon: 'only-trash', iconOnly: true}),
                rowAction({key: 'plain', label: 'Plain', icon: 'unknown-icon', iconOnly: true}),
            ], []],
        })

        const wrapper = mount(TableBuilder, {props: {table}})

        const remove = wrapper.find('[data-row-action="delete"]')
        const stop = wrapper.find('[data-row-action="stop"]')
        const show = wrapper.find('[data-row-action="show"]')

        expect(remove.text()).toBe('')
        expect(remove.attributes('aria-label')).toBe('Delete')
        expect(remove.attributes('title')).toBe('Delete')
        expect(remove.classes()).toContain('size-8')
        expect(stop.attributes('aria-label')).toBe('Stop: Busy')
        expect(stop.element.parentElement?.getAttribute('title')).toBe('Stop: Busy')
        expect(show.attributes('aria-label')).toBe('Show')
        expect(show.text()).toBe('')
        expect(wrapper.find('[data-row-action="plain"]').text()).toBe('Plain')
    })

    it('shows the spinner in place of the icon while the action runs', async () => {
        registerRowActionIcons({'only-power': icon('only-power')})
        vi.mocked(router.visit).mockImplementation(((_url: string, options: {onStart?: () => void}) => options.onStart?.()) as never)

        const table = gridData({
            layout: 'table',
            rowActions: [[rowAction({key: 'enable', label: 'Enable', method: 'put', icon: 'only-power', iconOnly: true})], []],
        })

        const wrapper = mount(TableBuilder, {props: {table}})

        await wrapper.find('[data-row-action="enable"]').trigger('click')
        await flush()

        const button = wrapper.find('[data-row-action="enable"]')

        expect(button.find('[data-row-action-spinner]').exists()).toBe(true)
        expect(button.find('svg[data-icon="only-power"]').exists()).toBe(false)
        expect(button.attributes('aria-label')).toBe('Enable')

        vi.mocked(router.visit).mockReset()
    })
})

describe('default row action icons', () => {
    it('registers the pencil and trash icons in one call', async () => {
        const {registerDefaultRowActionIcons, resolveRowActionIcon, DEFAULT_ROW_ACTION_ICONS} = await import('../../resources/js/lib/row-action-icons')

        registerDefaultRowActionIcons()

        expect(resolveRowActionIcon('pencil')).toBe(DEFAULT_ROW_ACTION_ICONS.pencil)
        expect(resolveRowActionIcon('trash-2')).toBe(DEFAULT_ROW_ACTION_ICONS['trash-2'])
    })
})
