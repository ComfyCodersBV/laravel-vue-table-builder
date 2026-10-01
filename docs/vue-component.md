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
| `reorderable` | `boolean` | No   | Adds a drag handle in front of every row. See [Reordering Rows](#reordering-rows)                                                                                                                                                                                                  |
| `rowClass` | `(row, index) => string \| string[] \| Record<string, boolean>` | No | Extra classes per row, for status colours and similar                                                                                                                                                                     |

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
| Clickable rows                      | `rowLinks` array is non-empty, or a `row-click` listener is bound |
| Drag handles                        | `reorderable` is set                      |
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

| Slot          | Scope                          | Renders                                                    |
|---------------|--------------------------------|------------------------------------------------------------|
| `cell-{key}`  | `row`, `value`, `index`, `column` | Replaces the cell body of the column named `{key}`      |
| `actions`     | `row`, `index`                 | A trailing column on every row, right aligned; clicks do not follow the row link |
| `toolbar`     | -                              | Controls next to the column selector above the table        |
| `row-after`   | `row`, `index`, `columns`, `colspan` | Extra rows under each row. See [Nested Rows](#nested-rows) |

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

## Events

| Event       | Payload                  | Fired when                                                                 |
|-------------|--------------------------|----------------------------------------------------------------------------|
| `reorder`   | `{from, to, row}`        | A row is dropped on another row of a `reorderable` table                   |
| `row-click` | `{row, index, event}`    | A row without a row link is clicked                                        |

### Reordering Rows

`reorderable` puts a drag handle in front of every row. Dropping a row on another emits `reorder`;
`from` and `to` are positions within the rows currently shown and `row` is the dragged row. The
component does not move anything itself: persist the new order and reload.

```vue

<script setup lang="ts">
    import {useTemplateRef} from 'vue'
    import {router} from '@inertiajs/vue3'

    const lines = useTemplateRef('lines')

    function move({row, to}: {from: number; to: number; row: any}) {
        router.post(`/lines/${row.id}/move`, {position: to}, {
            onSuccess: () => lines.value?.reload(),
        })
    }
</script>

<template>
    <TableBuilder ref="lines" :table="table" reorderable @reorder="move"/>
</template>
```

On a paginated table add the offset of the current page yourself, and keep in mind that a sorted
or filtered table shows a different order than the one you store.

### Clicking Rows

A row with a row link from the PHP builder follows that link. Every other row emits `row-click`, and
gets the pointer cursor as soon as a listener is bound, so an `http` table can open a record without
a server-side row link. Clicks on the drag handle, the selection checkbox, the `actions` slot and
columns marked `clickable: false` never reach it.

```vue

<TableBuilder :table="table" @row-click="({row}) => open(row)"/>
```

### Nested Rows

The `row-after` slot renders below each row, for option lines or grouped sub-rows. `colspan` is the
full width of a row including the handle, selection and actions columns, and `columns` are the
visible columns. `TableRow` and `TableCell` are exported so those rows look like the rest of the
table.

```vue

<script setup lang="ts">
    import {TableBuilder, TableCell, TableRow} from '@/components'
</script>

<template>
    <TableBuilder :table="table">
        <template #row-after="{row, colspan}">
            <TableRow v-for="option in row.options" :key="option.id" class="bg-muted/30">
                <TableCell :colspan="colspan" class="pl-10">{{ option.name }}</TableCell>
            </TableRow>
        </template>
    </TableBuilder>
</template>
```

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
