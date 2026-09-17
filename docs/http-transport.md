# HTTP Transport

By default `TableBuilder` drives its state through the url and lets Inertia reload the page props.
With `transport="http"` it fetches its own rows from `source` instead and keeps sort, filter, search,
per-page and page state in memory. The url is never touched, so the table can sit on a page that has
nothing to do with the data it shows.

Use it for a table fed by an external API, for a table inside a modal or tab that must not navigate,
and for several independent tables on one page.

## Usage

```vue

<script setup lang="ts">
    import {TableBuilder} from '@/components'
    import type {TableData} from '@/types/table-builder'

    defineProps<{ table: TableData }>()
</script>

<template>
    <TableBuilder
        :table="table"
        transport="http"
        source="https://api.example.com/v1/brands"
    />
</template>
```

The `table` prop is still required: it carries the columns, filters, search inputs and per-page
options that the component renders. Only the rows and the pagination come from `source`. Serialize an
empty builder for that:

```php
return Inertia::render('Brands/Index', [
    'table' => BrandsTable::make([])->toArray(),
]);
```

## The Request

The built-in request is a `GET` with `Accept: application/json` and these parameters:

| Parameter        | Sent when             |
|------------------|-----------------------|
| `sort`           | a column is sorted, prefixed with `-` for descending |
| `filter[key]`    | the filter has a value |
| `filter[global]` | the search input has a value |
| `page`           | past the first page   |
| `perPage`        | a per-page option is chosen |

Table names never namespace these parameters. Each `http` table has its own state, so there is
nothing to keep apart.

## Expected Response

```json
{
    "data": [{"id": 1, "name": "Acme"}],
    "pagination": {"current_page": 1, "last_page": 4, "per_page": 10, "total": 40, "from": 1, "to": 10}
}
```

A bare array is accepted as the rows without pagination, and `meta` is read when `pagination` is
absent. Anything else needs an `adapter`.

## Custom Request

`fetcher` replaces the request entirely. Use it for authentication headers, a different parameter
naming or a client you already have:

```vue

<script setup lang="ts">
    import type {TableFetcher} from '@/composables'

    const fetcher: TableFetcher = async (query, source) => {
        const response = await fetch(`${source}?page=${query.page}&q=${query.search}`, {
            headers: {Authorization: `Bearer ${token}`},
        })

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`)
        }

        return response.json()
    }
</script>

<template>
    <TableBuilder :table="table" transport="http" source="/api/brands" :fetcher="fetcher"/>
</template>
```

A thrown error is shown above the table.

## Custom Response Shape

`adapter` maps the response body onto the rows and the pagination:

```ts
const adapter: TableAdapter = (payload) => ({
    data: payload.results,
    pagination: {
        current_page: payload.page,
        last_page: payload.pages,
        per_page: payload.size,
        total: payload.count,
        from: null,
        to: null,
        links: [],
        first_page_url: null,
        last_page_url: null,
        next_page_url: null,
        prev_page_url: null,
    },
})
```

## Escaping

Rows that come from `source` are rendered with `v-html` and are not escaped by the PHP builder.
Escape them on the server, or render the column through a `cell-{key}` slot.

## Bulk Actions

Bulk actions post `{ids: [...]}` to the action url with same-origin credentials and the
`X-XSRF-TOKEN` header, so a Laravel web route accepts them. The url is still signed by the PHP
builder. After a successful response the selection is cleared and the table refetches.

## Using the Composable Directly

`useTableTransport` holds all of this and is exported for custom table components:

```ts
import {useTableTransport} from '@tranquil-tools/laravel-vue-table-builder'

const {table, loading, error, sortFor, applySort, applyFilter, applySearch, applyPerPage, applyPage, reload} =
    useTableTransport({
        table: computed(() => props.table),
        transport: computed(() => props.transport),
        source: computed(() => props.source),
        name: computed(() => props.name ?? 'default'),
    })
```

Changing `source` or `transport` after mount reloads the table. A response that a newer request has
overtaken is discarded, so fast typing cannot leave stale rows on screen.
