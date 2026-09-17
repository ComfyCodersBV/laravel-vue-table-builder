import {computed, ref, watch, type Ref} from 'vue'
import {router} from '@inertiajs/vue3'
import type {Column, PaginationData, TableData} from '../types/table-builder'

export type TableTransport = 'inertia' | 'http'

export interface TableQuery {
    sort: string | null
    filters: Record<string, string>
    search: string
    page: number
    perPage: number | null
}

export interface TableResponse {
    data: any[]
    pagination?: PaginationData | null
}

export type TableFetcher = (query: TableQuery, source: string) => Promise<unknown>

export type TableAdapter = (payload: any) => TableResponse

export interface TableTransportOptions {
    table: Ref<TableData>
    transport: Ref<TableTransport>
    source: Ref<string | undefined>
    name: Ref<string>
    only?: string[]
    fetcher?: TableFetcher
    adapter?: TableAdapter
}

function readInitialQuery(table: TableData): TableQuery {
    return {
        sort: table.defaultSort || null,
        filters: Object.fromEntries(
            (table.filters ?? [])
                .filter((filter) => filter.value)
                .map((filter) => [filter.key, String(filter.value)])
        ),
        search: table.searchInputs?.global?.value ?? '',
        page: table.pagination?.current_page ?? 1,
        perPage: table.pagination?.per_page ?? null,
    }
}

function defaultAdapter(payload: any): TableResponse {
    if (Array.isArray(payload)) {
        return {data: payload, pagination: null}
    }

    return {
        data: payload?.data ?? [],
        pagination: payload?.pagination ?? payload?.meta ?? null,
    }
}

export function xsrfHeaders(): Record<string, string> {
    const token = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)

    return token ? {'X-XSRF-TOKEN': decodeURIComponent(token[1])} : {}
}

const defaultFetcher: TableFetcher = async (query, source) => {
    const url = new URL(source, window.location.origin)

    if (query.sort) {
        url.searchParams.set('sort', query.sort)
    }

    Object.entries(query.filters).forEach(([key, value]) => {
        if (value) {
            url.searchParams.set(`filter[${key}]`, value)
        }
    })

    if (query.search) {
        url.searchParams.set('filter[global]', query.search)
    }

    if (query.page > 1) {
        url.searchParams.set('page', String(query.page))
    }

    if (query.perPage) {
        url.searchParams.set('perPage', String(query.perPage))
    }

    const response = await fetch(url.toString(), {headers: {Accept: 'application/json'}})

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`)
    }

    return response.json()
}

export function useTableTransport(options: TableTransportOptions) {
    const {table: initial, transport, source, name} = options

    const query = ref<TableQuery>(readInitialQuery(initial.value))
    const fetched = ref<TableResponse | null>(null)
    const loading = ref(false)
    const error = ref<string | null>(null)

    const isHttp = computed(() => transport.value === 'http')

    const namespacedKey = (key: string) => (name.value !== 'default' ? `${name.value}_${key}` : key)
    const filterKey = (key: string) => (name.value !== 'default' ? `${name.value}_filter[${key}]` : `filter[${key}]`)

    const table = computed<TableData>(() => {
        const base = initial.value

        if (!isHttp.value) {
            return base
        }

        return {
            ...base,
            data: fetched.value?.data ?? [],
            pagination: fetched.value?.pagination ?? undefined,
            filters: (base.filters ?? []).map((filter) => ({
                ...filter,
                value: query.value.filters[filter.key] ?? null,
            })),
            searchInputs: {
                ...base.searchInputs,
                ...(base.searchInputs?.global
                    ? {global: {...base.searchInputs.global, value: query.value.search}}
                    : {}),
            },
        }
    })

    let latestRequest = 0

    async function load(): Promise<void> {
        if (!source.value) {
            error.value = 'A source url is required when using the http transport.'

            return
        }

        const request = ++latestRequest

        loading.value = true
        error.value = null

        try {
            const adapter = options.adapter ?? defaultAdapter
            const fetcher = options.fetcher ?? defaultFetcher

            const payload = await fetcher(query.value, source.value)

            if (request !== latestRequest) {
                return
            }

            fetched.value = adapter(payload)
        } catch (exception) {
            if (request !== latestRequest) {
                return
            }

            error.value = exception instanceof Error ? exception.message : String(exception)
        } finally {
            if (request === latestRequest) {
                loading.value = false
            }
        }
    }

    function visit(params: URLSearchParams): void {
        router.get(window.location.pathname + '?' + params.toString(), {}, {
            preserveState: true,
            preserveScroll: true,
            ...(options.only ? {only: options.only} : {}),
        })
    }

    function currentParams(): URLSearchParams {
        return new URLSearchParams(window.location.search)
    }

    function defaultSortKey(): string | null {
        return initial.value.defaultSort ? String(initial.value.defaultSort).replace(/^-/, '') : null
    }

    function sortFor(column: Column): 'asc' | 'desc' | false {
        if (isHttp.value) {
            if (!query.value.sort) {
                return false
            }

            const descending = query.value.sort.startsWith('-')
            const key = query.value.sort.replace(/^-/, '')

            return key === column.key ? (descending ? 'desc' : 'asc') : false
        }

        if (column.sorted) {
            return column.sorted as 'asc' | 'desc'
        }

        if (currentParams().has(namespacedKey('sort'))) {
            return false
        }

        if (!initial.value.defaultSort) {
            return false
        }

        const defaultDirection = String(initial.value.defaultSort).startsWith('-') ? 'desc' : 'asc'

        return column.key === defaultSortKey() ? (defaultDirection as 'asc' | 'desc') : false
    }

    function nextSort(column: Column): 'asc' | 'desc' | false {
        const current = sortFor(column)

        if (current === 'asc') {
            return 'desc'
        }

        if (current !== 'desc') {
            return 'asc'
        }

        return column.key === defaultSortKey() ? 'asc' : false
    }

    function applySort(column: Column): void {
        if (!column.sortable) {
            return
        }

        const direction = nextSort(column)

        if (isHttp.value) {
            query.value.sort = direction ? (direction === 'desc' ? `-${column.key}` : column.key) : null
            query.value.page = 1

            return void load()
        }

        const params = currentParams()

        if (direction) {
            params.set(namespacedKey('sort'), direction === 'desc' ? `-${column.key}` : column.key)
        } else {
            params.delete(namespacedKey('sort'))
        }

        visit(params)
    }

    function applyFilter(key: string, value: string): void {
        if (isHttp.value) {
            if (value) {
                query.value.filters[key] = value
            } else {
                delete query.value.filters[key]
            }

            query.value.page = 1

            return void load()
        }

        const params = currentParams()

        if (value) {
            params.set(filterKey(key), value)
        } else {
            params.delete(filterKey(key))
        }

        params.delete(namespacedKey('page'))

        visit(params)
    }

    function applySearch(value: string): void {
        if (isHttp.value) {
            query.value.search = value
            query.value.page = 1

            return void load()
        }

        const params = currentParams()

        if (value) {
            params.set(filterKey('global'), value)
        } else {
            params.delete(filterKey('global'))
        }

        params.delete(namespacedKey('page'))

        visit(params)
    }

    function applyPerPage(value: string): void {
        if (isHttp.value) {
            query.value.perPage = Number(value)
            query.value.page = 1

            return void load()
        }

        const params = currentParams()
        params.set(namespacedKey('perPage'), value)
        params.delete(namespacedKey('page'))

        visit(params)
    }

    function applyPage(direction: -1 | 1): void {
        const current = table.value.pagination?.current_page ?? 1
        const target = current + direction

        if (isHttp.value) {
            query.value.page = Math.max(target, 1)

            return void load()
        }

        const params = currentParams()

        if (target <= 1) {
            params.delete(namespacedKey('page'))
        } else {
            params.set(namespacedKey('page'), String(target))
        }

        visit(params)
    }

    function reload(): void {
        if (isHttp.value) {
            return void load()
        }

        router.reload({preserveScroll: true, ...(options.only ? {only: options.only} : {})})
    }

    if (isHttp.value) {
        void load()
    }

    watch([source, transport], () => {
        if (isHttp.value) {
            void load()
        }
    })

    return {
        table,
        query,
        loading,
        error,
        isHttp,
        sortFor,
        applySort,
        applyFilter,
        applySearch,
        applyPerPage,
        applyPage,
        reload,
    }
}
