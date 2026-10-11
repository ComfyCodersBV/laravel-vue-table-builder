export interface Column {
    key: string
    label: string
    can_be_hidden: boolean
    hidden: boolean
    sortable: boolean
    sorted: 'asc' | 'desc' | false
    highlight: boolean
    class: string
    alignment: string
    clickable: boolean
    boolean: boolean
}

export interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

export interface PaginationData {
    current_page: number
    from: number | null
    to: number | null
    total: number
    per_page: number
    last_page: number
    links: PaginationLink[]
    first_page_url: string | null
    last_page_url: string | null
    next_page_url: string | null
    prev_page_url: string | null
}

export interface Filter {
    key: string
    label: string
    options: Record<string, string>
    value?: string | null
    type: 'select' | 'text' | 'date' | 'date_range'
}

export interface SearchInput {
    key: string
    label: string
    value?: string | null
}

export interface BulkAction {
    key: string
    label: string
    url: string
    confirm: boolean | string
    confirmText: string
    confirmButton: string
    cancelButton: string
    requirePassword: boolean | string
}

export type RowActionMethod = 'get' | 'post' | 'put' | 'patch' | 'delete'

export type RowActionVariant = 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link'

export interface RowAction {
    key: string
    label: string
    url: string
    method: RowActionMethod
    confirm: string | null
    variant: RowActionVariant
    disabled: boolean
    disabledReason: string | null
    download?: boolean | string
}

export interface EmptyState {
    title?: string | null
    description?: string | null
    filteredTitle?: string | null
    filteredDescription?: string | null
}

export interface ReorderConfig {
    column: string
    key: string
    url: string | null
}

export interface QueryKeys {
    sort: string
    filter: string
    page: string
    perPage: string
    columns: string
}

export interface TableData {
    name?: string
    data: any[]
    columns: Column[]
    pagination?: PaginationData
    filters: Filter[]
    searchInputs: Record<string, SearchInput>
    perPageOptions: number[]
    defaultSort: string
    bulkActions: BulkAction[]
    rowLinks: (string | null)[]
    rowLinkType: 'modal' | 'link' | 'href' | ''
    rowLinkTarget: '_self' | '_blank'
    cellClass?: string
    headClass?: string
    rowActions?: RowAction[][]
    emptyState?: EmptyState
    reorder?: ReorderConfig | null
    queryKeys?: QueryKeys
}