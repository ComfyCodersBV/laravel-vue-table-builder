import {describe, expect, it, vi} from 'vitest'
import {computed, nextTick, ref} from 'vue'
import {useTableTransport, type TableQuery} from '../../resources/js/composables/useTableTransport'
import type {Column, TableData} from '../../resources/js/types/table-builder'

vi.mock('@inertiajs/vue3', () => ({
    router: {get: vi.fn(), reload: vi.fn(), post: vi.fn()},
}))

function column(key: string, overrides: Partial<Column> = {}): Column {
    return {
        key,
        label: key,
        can_be_hidden: false,
        hidden: false,
        sortable: true,
        sorted: false,
        highlight: false,
        class: '',
        alignment: '',
        clickable: true,
        boolean: false,
        ...overrides,
    }
}

function tableData(overrides: Partial<TableData> = {}): TableData {
    return {
        name: 'brands',
        data: [],
        columns: [column('ident'), column('name')],
        filters: [{key: 'name', label: 'Name', options: {}, value: null, type: 'text'}],
        searchInputs: {global: {key: 'global', label: 'Search', value: ''}},
        perPageOptions: [10, 25],
        defaultSort: 'ident',
        bulkActions: [],
        rowLinks: [],
        rowLinkType: '',
        rowLinkTarget: '_self',
        ...overrides,
    }
}

function setup(options: {fetcher?: any; adapter?: any; table?: TableData} = {}) {
    const calls: TableQuery[] = []

    const fetcher = options.fetcher ?? vi.fn(async (query: TableQuery) => {
        calls.push(JSON.parse(JSON.stringify(query)))

        return {
            data: [{ident: 'ACME', name: 'Acme'}],
            pagination: {
                current_page: query.page,
                from: 1,
                to: 1,
                total: 40,
                per_page: query.perPage ?? 10,
                last_page: 4,
                links: [],
                first_page_url: null,
                last_page_url: null,
                next_page_url: null,
                prev_page_url: null,
            },
        }
    })

    const transport = useTableTransport({
        table: computed(() => options.table ?? tableData()),
        transport: ref('http'),
        source: ref('https://api.pedroshop.nl/admin/v1/brands.json'),
        name: ref('brands'),
        fetcher,
        adapter: options.adapter,
    })

    return {transport, fetcher, calls}
}

describe('useTableTransport with the http transport', () => {
    it('loads rows from the source instead of visiting a url', async () => {
        const {transport, fetcher} = setup()

        await nextTick()
        await nextTick()

        expect(fetcher).toHaveBeenCalledOnce()
        expect(transport.table.value.data).toEqual([{ident: 'ACME', name: 'Acme'}])
        expect(transport.table.value.pagination?.total).toBe(40)
    })

    it('toggles sort direction and refetches', async () => {
        const {transport, calls} = setup()
        await nextTick()

        const nameColumn = transport.table.value.columns[1]

        transport.applySort(nameColumn)
        await nextTick()
        expect(transport.sortFor(nameColumn)).toBe('asc')

        transport.applySort(nameColumn)
        await nextTick()
        expect(transport.sortFor(nameColumn)).toBe('desc')

        expect(calls.at(-1)?.sort).toBe('-name')
    })

    it('resets to the first page when a filter changes', async () => {
        const {transport, calls} = setup()
        await nextTick()

        transport.applyPage(1)
        await nextTick()
        expect(calls.at(-1)?.page).toBe(2)

        transport.applyFilter('name', 'acme')
        await nextTick()

        expect(calls.at(-1)?.page).toBe(1)
        expect(calls.at(-1)?.filters).toEqual({name: 'acme'})
    })

    it('clears a filter when an empty value is applied', async () => {
        const {transport, calls} = setup()
        await nextTick()

        transport.applyFilter('name', 'acme')
        await nextTick()
        transport.applyFilter('name', '')
        await nextTick()

        expect(calls.at(-1)?.filters).toEqual({})
    })

    it('never pages below the first page', async () => {
        const {transport, calls} = setup()
        await nextTick()

        transport.applyPage(-1)
        await nextTick()

        expect(calls.at(-1)?.page).toBe(1)
    })

    it('reflects the active search value back onto the search input', async () => {
        const {transport, calls} = setup()
        await nextTick()

        transport.applySearch('acme')
        await nextTick()

        expect(calls.at(-1)?.search).toBe('acme')
        expect(transport.table.value.searchInputs.global.value).toBe('acme')
    })

    it('surfaces a failing request as an error and stops loading', async () => {
        const {transport} = setup({
            fetcher: vi.fn(async () => {
                throw new Error('Request failed with status 500')
            }),
        })

        await nextTick()
        await nextTick()

        expect(transport.error.value).toBe('Request failed with status 500')
        expect(transport.loading.value).toBe(false)
    })

    it('discards a slow response that a newer request has overtaken', async () => {
        const responses: Array<(value: unknown) => void> = []

        const fetcher = vi.fn((query: TableQuery) => new Promise((resolve) => {
            responses.push(() => resolve({data: [{ident: query.search || 'initial'}], pagination: null}))
        }))

        const {transport} = setup({fetcher})
        await nextTick()

        transport.applySearch('acme')
        await nextTick()

        responses[1]()
        await nextTick()
        await nextTick()

        responses[0]()
        await nextTick()
        await nextTick()

        expect(transport.table.value.data).toEqual([{ident: 'acme'}])
        expect(transport.loading.value).toBe(false)
    })

    it('reloads when the source url changes', async () => {
        const fetcher = vi.fn(async () => ({data: [], pagination: null}))
        const source = ref<string | undefined>('https://api.pedroshop.nl/admin/v1/brands.json')

        useTableTransport({
            table: computed(() => tableData()),
            transport: ref('http'),
            source,
            name: ref('brands'),
            fetcher,
        })

        await nextTick()
        expect(fetcher).toHaveBeenCalledOnce()

        source.value = 'https://api.pedroshop.nl/admin/v1/categories.json'
        await nextTick()

        expect(fetcher).toHaveBeenCalledTimes(2)
    })

    it('maps a payload through a custom adapter', async () => {
        const {transport} = setup({
            fetcher: vi.fn(async () => ({rows: [{ident: 'X'}], meta: {count: 1}})),
            adapter: (payload: any) => ({data: payload.rows, pagination: null}),
        })

        await nextTick()
        await nextTick()

        expect(transport.table.value.data).toEqual([{ident: 'X'}])
    })
})

describe('useTableTransport with the inertia transport', () => {
    it('does not fetch on mount', async () => {
        const fetcher = vi.fn()

        useTableTransport({
            table: computed(() => tableData()),
            transport: ref('inertia'),
            source: ref(undefined),
            name: ref('brands'),
            fetcher,
        })

        await nextTick()

        expect(fetcher).not.toHaveBeenCalled()
    })

    it('reports an error when the http transport has no source', async () => {
        const transport = useTableTransport({
            table: computed(() => tableData()),
            transport: ref('http'),
            source: ref(undefined),
            name: ref('brands'),
        })

        await nextTick()

        expect(transport.error.value).toBe('A source url is required when using the http transport.')
    })
})
