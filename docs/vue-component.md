# Vue Component

## Import

```vue

<script setup lang="ts">
    import {TableBuilder} from '@/components'
    import type {TableData} from '@/types/table-builder'

    defineProps<{ table: TableData }>()
</script>

<template>
    <TableBuilder :table="table"/>
</template>
```

The component is exported from the package's `resources/js/components/` directory, which is aliased via `@`.

## Props

| Prop    | Type        | Required | Description                                                                                                                                                                                                                                                                          |
|---------|-------------|----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `table` | `TableData` | Yes      | The serialized table data from the PHP `TableBuilder`                                                                                                                                                                                                                                |
| `name`  | `string`    | No       | Overrides the table name used to namespace query params (`{name}_page`, `{name}_perPage`). Usually unnecessary: the name is read from the serialized `table` payload (derived from the table class), so it only needs setting for inline tables where you called `->name()` manually |
| `only`  | `string[]`  | No       | Inertia prop name(s) to reload on pagination/per-page changes, enables partial reloads so only this table's data is fetched                                                                                                                                                          |
| `paginationPosition` | `'top'\|'bottom'\|'both'` | No | Where the pagination controls render. Defaults to `bottom`; `top` puts a compact version next to the column selector                                                                                                                             |
| `transport` | `'inertia'\|'http'` | No | How the table fetches its rows. Defaults to `inertia`. See [HTTP Transport](http-transport.md)                                                                                                                                                                       |
| `source` | `string`   | No       | Url the `http` transport fetches from. Required with `transport="http"`                                                                                                                                                                                                             |
| `fetcher` | `TableFetcher` | No  | Replaces the built-in request of the `http` transport                                                                                                                                                                                                                               |
| `adapter` | `TableAdapter` | No  | Maps a non-standard response body onto `{data, pagination}`                                                                                                                                                                                                                         |

## Features Rendered

The component renders all of the following automatically based on what the PHP builder provides:

| Feature                             | Rendered when                             |
|-------------------------------------|-------------------------------------------|
| Column headers with sort indicators | columns exist                             |
| Column visibility dropdown          | any column has `can_be_hidden: true`      |
| Filter dropdowns                    | filters array is non-empty                |
| Search inputs                       | searchInputs map is non-empty             |
| Pagination controls                 | `pagination` object is present            |
| Per-page selector                   | `perPageOptions` has more than one option |
| Bulk action toolbar                 | `bulkActions` array is non-empty          |
| Row checkboxes                      | bulk actions present                      |
| Clickable rows                      | `rowLinks` array is non-empty             |
| Reset button                        | any filter/search/sort is active          |

## User Interactions

With the default `inertia` transport every interaction makes an Inertia visit with `preserveState: true` and
`preserveScroll: true` so the page does not fully reload. With the `http` transport the same interactions change the
in-memory query and refetch from `source` instead; the url is left alone. See [HTTP Transport](http-transport.md).

| Interaction                   | Query Param Changed                 |
|-------------------------------|-------------------------------------|
| Click sortable column header  | `sort=column` or `sort=-column`     |
| Toggle column visibility      | `columns[]=key` list                |
| Change filter dropdown        | `filter[key]=value`                 |
| Type in search input          | `filter[key]=term` (debounced)      |
| Change page                   | `{name}_page=N` (or `page=N`)       |
| Change per-page               | `{name}_perPage=N` (or `perPage=N`) |
| Select rows + run bulk action | POST to signed URL                  |

## Search Debounce

Search inputs are debounced to avoid firing on every keystroke. The default is 350ms. Change it globally:

```php
TableBuilder::defaultSearchDebounce(500); // ms
```

## Dark Mode

The component respects the `dark` class on the `<html>` element and uses Tailwind's dark mode utilities throughout.

## Customizing Appearance

### Global cell/header classes

```php
$table->class(cell: 'py-3 text-sm', head: 'bg-muted font-semibold');
```

### Per-column classes

```php
->column('id', 'ID', classes: 'w-16 tabular-nums')
->column('id', 'ID', classes: ['w-16' => true, 'hidden' => $compact])
```

The resolved classes arrive in the `class` key of the column payload.

### CSS Variables

The package uses shadcn/ui-style CSS variables. Override in your `app.css`:

```css
:root {
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    /* ... */
}
```

## Slots

The component emits no events. Everything you add lives in a slot.

| Slot          | Scope                          | Renders                                                    |
|---------------|--------------------------------|------------------------------------------------------------|
| `cell-{key}`  | `row`, `value`, `index`, `column` | Replaces the cell body of the column named `{key}`      |
| `actions`     | `row`, `index`                 | A trailing column on every row, right aligned; clicks do not follow the row link |
| `toolbar`     | -                              | Controls next to the column selector above the table        |

```vue

<TableBuilder :table="table">
    <template #cell-status="{value}">
        <Badge :variant="value === 'active' ? 'default' : 'secondary'">{{ value }}</Badge>
    </template>

    <template #actions="{row}">
        <Button size="sm" @click="edit(row)">Edit</Button>
    </template>

    <template #toolbar>
        <Button variant="outline" @click="exportCsv">Export</Button>
    </template>
</TableBuilder>
```

Without a `cell-{key}` slot the value is rendered with `v-html`. The PHP builder escapes string
values before they are serialized, so that is safe for tables driven by the builder. It is not safe
for rows fetched from an API with the `http` transport: escape those yourself, or render them
through a `cell-{key}` slot.

## Exposed Methods

```vue

<script setup lang="ts">
    import {useTemplateRef} from 'vue'

    const table = useTemplateRef('table')

    function refresh() {
        table.value?.reload()
    }
</script>

<template>
    <TableBuilder ref="table" :table="table"/>
</template>
```

| Method           | Returns      | Description                                                          |
|------------------|--------------|----------------------------------------------------------------------|
| `reload()`       | `void`       | Refetches the rows (`http`) or reloads the Inertia props (`inertia`) |
| `currentQuery()` | `TableQuery` | The active sort, filters, search, page and per-page                  |

## TypeScript

Import types for use in your pages:

```ts
import type {TableData, Column, Filter, BulkAction, PaginationData} from '@/types/table-builder'
```

See [TypeScript Types](typescript.md) for the full interface reference.
