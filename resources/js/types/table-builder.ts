export interface Column {
    key: string
    label: string
    sortable?: boolean
    sorted?: 'asc' | 'desc' | false
    hidden?: boolean
    class?: string
    headerClass?: string
    clickable?: boolean
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
    options: Array<{ label: string; value: string | number }>
    value?: string | number
    type?: 'select' | 'text'
}

export interface SearchInput {
    key: string
    label: string
    value?: string
}

export interface TableData {
    data: any[]
    columns: Column[]
    pagination?: PaginationData
    filters?: Filter[]
    searchInputs?: SearchInput[]
    perPageOptions?: number[]
    defaultSort?: string
}

export interface TableSchema {
    id?: string
    name: string
    resource: TableData
}
