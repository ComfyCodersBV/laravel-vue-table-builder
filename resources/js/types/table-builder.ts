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
    type: 'select' | 'text'
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

export interface TableData {
    data: any[]
    columns: Column[]
    pagination?: PaginationData
    filters: Filter[]
    searchInputs: Record<string, SearchInput>
    perPageOptions: number[]
    defaultSort: string
    bulkActions: BulkAction[]
    rowLinks: (string | null)[]
    rowLinkType: 'modal' | 'link' | ''
    cellClass?: string
    headClass?: string
}