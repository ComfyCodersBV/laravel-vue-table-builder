# TypeScript Types

All types are exported from `@/types/table-builder`.

```ts
import type {
    TableData,
    Column,
    PaginationData,
    PaginationLink,
    Filter,
    SearchInput,
    BulkAction,
} from '@/types/table-builder'
```

## `TableData`

The top-level prop type for `<TableBuilder>`. Matches the array returned by `TableBuilder::toArray()`.

```ts
interface TableData {
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
```

## `Column`

```ts
interface Column {
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
```

## `PaginationData`

Present when the resource was paginated. Absent when using `noPagination()` or a plain collection.

```ts
interface PaginationData {
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

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}
```

## `Filter`

```ts
interface Filter {
    key: string
    label: string
    options: Record<string, string>   // { value: 'Label' }
    value?: string | null
    type: 'select' | 'text'
}
```

## `SearchInput`

```ts
interface SearchInput {
    key: string
    label: string
    value?: string | null
}
```

## `BulkAction`

```ts
interface BulkAction {
    key: string
    label: string
    url: string              // signed POST URL
    confirm: boolean | string
    confirmText: string
    confirmButton: string
    cancelButton: string
    requirePassword: boolean | string
}
```

## Usage in Vue Pages

```vue
<script setup lang="ts">
import type { TableData } from '@/types/table-builder'

defineProps<{
    table: TableData
}>()
</script>
```

## Typing Table Data from Inertia

When using `usePage()` to access Inertia shared props:

```ts
import { usePage } from '@inertiajs/vue3'
import type { TableData } from '@/types/table-builder'

const page = usePage<{ table: TableData }>()
const table = page.props.table
```
